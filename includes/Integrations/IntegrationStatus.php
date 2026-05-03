<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

/**
 * Standard state vocabulary for integrations that depend on Sitewide delivery.
 */
final class IntegrationStatus
{
    public const CONFIGURED = 'configured';
    public const WAITING_FOR_SITEWIDE_DELIVERY = 'waiting_for_sitewide_delivery';
    public const LIVE = 'live';
    public const NEEDS_REAPPLY = 'needs_reapply';

    private function __construct(
        private readonly bool $available,
        private readonly bool $configured,
        private readonly bool $synced,
        private readonly bool $applied,
        private readonly bool $sitewideDelivery,
        private readonly string $humanStatus
    ) {
    }

    public static function fromState(
        bool $available,
        bool $configured,
        bool $synced,
        bool $applied,
        bool $sitewideDelivery
    ): self {
        $humanStatus = self::CONFIGURED;

        if (!$configured) {
            $humanStatus = self::CONFIGURED;
        } elseif (!$sitewideDelivery) {
            $humanStatus = self::WAITING_FOR_SITEWIDE_DELIVERY;
        } elseif ($applied) {
            $humanStatus = $synced ? self::LIVE : self::NEEDS_REAPPLY;
        }

        return new self($available, $configured, $synced, $applied, $sitewideDelivery, $humanStatus);
    }

    public function humanStatus(): string
    {
        return $this->humanStatus;
    }

    /**
     * @return array{available: bool, configured: bool, synced: bool, applied: bool, sitewide_delivery: bool, human_status: string}
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'configured' => $this->configured,
            'synced' => $this->synced,
            'applied' => $this->applied,
            'sitewide_delivery' => $this->sitewideDelivery,
            'human_status' => $this->humanStatus,
        ];
    }
}
