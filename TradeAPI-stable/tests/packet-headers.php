<?php

declare(strict_types=1);

$autoload = getenv('AXOLOTL_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) {
	throw new RuntimeException('Set AXOLOTL_AUTOLOAD to a verified Axolotl-PM Composer autoloader');
}
require $autoload;
require __DIR__ . '/../src/nlog/trade/TradePacketFactory.php';

use nlog\trade\TradePacketFactory;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;

$actorId = 73;
$position = new Vector3(2, 3, 4);
$metadata = [EntityMetadataProperties::TRADE_TIER => new IntMetadataProperty(2)];
$add = TradePacketFactory::fakeTrader($actorId, $position, $metadata);
if ($add->actorUniqueId !== $actorId || $add->actorRuntimeId !== $actorId || $add->type !== EntityIds::NPC ||
	$add->position !== $position || $add->metadata !== $metadata ||
	$add->syncedProperties->getIntProperties() !== [] || $add->syncedProperties->getFloatProperties() !== []) {
	throw new RuntimeException('Fake trader packet does not initialize the current protocol fields');
}
$remove = TradePacketFactory::removeFakeTrader($actorId);
if ($remove->actorUniqueId !== $actorId) {
	throw new RuntimeException('Fake trader removal does not use the actor unique ID');
}

echo "PASS: archived trader actor packet fields\n";
