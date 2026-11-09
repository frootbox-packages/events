<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use DateTimeImmutable;
use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Auth\Permissions;
use Frootbox\Core\Page\PageOutline;
use Frootbox\Core\Page\PageProtections;
use Frootbox\Core\Security\Secrets;
use Frootbox\Core\Url\Target;

/**
 * Who may use a restricted channel (decision 0360): blocks only on a page
 * with a password, queries and the booking with a signed access that such
 * a page hands out, or a user with the right to read the names. The access
 * names channel, page and generation of its password and holds a day, so a
 * new password or switching it off ends it.
 */
final readonly class ChannelAccess
{
    /** The permission that opens restricted channels without access. */
    public const string PERMISSION = 'frootbox/events/attendee.list';

    private const string PURPOSE = 'events-channel-access';

    private const int SECONDS = 86400;

    public function __construct(
        private Courses $courses,
        private PageOutline $outline,
        private PageProtections $protections,
        private Secrets $secrets,
        private CurrentContext $context,
        private Permissions $permissions,
    ) {}

    public function restricted(string $channel): bool
    {
        return $this->courses->choice($channel)->restricted ?? false;
    }

    /**
     * Whether a block may show the channel where it is delivered.
     */
    public function shows(string $channel, ?Target $target): bool
    {
        return !$this->restricted($channel) || null !== $this->protectedBy($target);
    }

    /**
     * Whether the page where a block is delivered has a password.
     */
    public function protectedBy(?Target $target): ?string
    {
        return null !== $target && Target::PAGE === $target->type ? $this->outline->protectedBy($target->id) : null;
    }

    /**
     * The access for a form or link of a block with a restricted channel;
     * null for another channel or a page without password.
     */
    public function issue(string $channel, ?Target $target, ?DateTimeImmutable $now = null): ?string
    {
        $page = $this->protectedBy($target);
        $generation = null === $page ? null : $this->protections->of($page)?->generation;

        if (!$this->restricted($channel) || null === $page || null === $generation) {
            return null;
        }

        $payload = implode('|', [$channel, $page, $generation, (string) (($now ?? new DateTimeImmutable())->getTimestamp() + self::SECONDS)]);
        $signature = $this->secrets->sign(self::PURPOSE, $payload);

        return null === $signature ? null : self::encode($payload) . '.' . $signature;
    }

    /**
     * Throws unless the caller may use the channel: any open one, a
     * restricted one with a valid access or the permission.
     *
     * @throws AccessDeniedException
     */
    public function check(string $channel, ?string $access, ?DateTimeImmutable $now = null): void
    {
        if (!$this->restricted($channel) || $this->valid($channel, $access, $now)) {
            return;
        }

        $actor = $this->context->get()->actor;

        if (!$this->permissions->allows($actor, self::PERMISSION)) {
            throw AccessDeniedException::forPermission(self::PERMISSION, $actor);
        }
    }

    private function valid(string $channel, ?string $access, ?DateTimeImmutable $now): bool
    {
        [$encoded, $signature] = explode('.', $access ?? '', 2) + [1 => ''];
        $payload = self::decode($encoded);

        if (null === $payload || '' === $signature || !$this->secrets->verify(self::PURPOSE, $payload, $signature)) {
            return false;
        }

        [$key, $page, $generation, $until] = explode('|', $payload) + ['', '', '', '0'];

        return $key === $channel
            && (int) $until >= ($now ?? new DateTimeImmutable())->getTimestamp()
            && $this->outline->protectedBy($page) === $page
            && $this->protections->of($page)?->generation === $generation;
    }

    private static function encode(string $text): string
    {
        return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
