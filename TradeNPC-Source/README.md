# TradeNPC

This commit establishes the API 4 TradeNPC implementation as the canonical
source baseline. The following migration commit ports this source to
PocketMine-MP API 5; the API 4 implementation remains recoverable from this
commit instead of being kept as a parallel runtime source.

## API baseline

The plugin manifest targets PocketMine-MP API `4.0.0`. This snapshot is kept
for migration provenance and is not intended to be installed alongside the
API 5 result.
