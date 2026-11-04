<?php

/* SPDX-License-Identifier: AGPL-3.0-or-later. See the snapshot LICENSE. */

namespace nlog\trade;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;

final class TradePacketFactory {

	/** @param array<int, \pocketmine\network\mcpe\protocol\types\entity\MetadataProperty> $metadata */
	public static function fakeTrader(int $actorId, Vector3 $position, array $metadata): AddActorPacket {
		$packet = new AddActorPacket();
		$packet->actorUniqueId = $actorId;
		$packet->actorRuntimeId = $actorId;
		$packet->type = EntityIds::NPC;
		$packet->position = $position;
		$packet->metadata = $metadata;
		$packet->syncedProperties = new PropertySyncData([], []);
		return $packet;
	}

	public static function removeFakeTrader(int $actorId): RemoveActorPacket {
		return RemoveActorPacket::create($actorId);
	}
}
