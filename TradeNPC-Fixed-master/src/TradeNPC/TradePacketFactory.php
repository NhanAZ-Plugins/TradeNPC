<?php

declare(strict_types=1);

namespace TradeNPC;

use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;

final class TradePacketFactory
{
    public static function closeTradeWindow(): ContainerClosePacket
    {
        $packet = new ContainerClosePacket();
        $packet->windowId = 255;
        $packet->server = false;
        self::setOptionalIntProperty($packet, 'windowType', WindowTypes::TRADING);
        return $packet;
    }

    private static function setOptionalIntProperty(object $packet, string $property, int $value): void
    {
        if (property_exists($packet, $property)) {
            $packet->{$property} = $value;
        }
    }
}
