# TradeNPC

TradeNPC is an Axolotl-PM API 5 plugin for creating named trading NPCs with
`/npc create`, configuring offers with `/npc setitem`, and removing a saved
NPC with `/npc remove`.

The repository keeps a linear migration history: the API 4 implementation is
the source baseline commit, the next migration commit ports it to API 5, and
the repository root contains the integrated generic TradeAPI module. The API 4
source is therefore recoverable from Git history without maintaining a second
runtime source directory.

## Compatibility

The plugin manifest declares API `5.0.0`. CI analyzes the source and builds one
TradeNPC PHAR against pinned Axolotl-PM 5.0.0 and 5.51.1 source revisions.
These tested revisions do not establish compatibility with every 5.x release
or verify the in-game Bedrock trading screen.

## TradeAPI module

The generic TradeAPI is available under the existing `nlog\trade` namespace so
external plugins can continue using `TradeAPI`, `MerchantRecipe`,
`MerchantRecipeList`, and `TraderProperties`. It is initialized by
`TradeNPC\Main`; it is no longer a second PocketMine plugin and does not have
a separate `plugin.yml`.

Example code is in [`examples/TradeTest.php`](examples/TradeTest.php). A plugin
using the module should declare `TradeNPC` as a soft dependency and call
`nlog\trade\TradeAPI::getInstance()` after TradeNPC has enabled.

## Trade safety

The NPC trade handler validates the selected offer, both required inputs, and
room for the output before settling a trade. Incomplete offers and pre-login
packets are ignored. Normal inventory actions remain subject to server
validation. Regression fixtures cover packet guards, complete recipe inputs,
settlement, persisted NPC offers, and the integrated TradeAPI inventory path.

## Build

The repository workflow uses [DevTools](https://github.com/NhanAZ/DevTools) and
a pinned [InvMenu](https://github.com/Muqsit/InvMenu) source revision to analyze
the source, build the single plugin PHAR, and verify its contents and checksum.
Review the [build workflow](.github/workflows/build.yml) for the exact tested
server commits and run evidence.

## License

The TradeNPC source retains its [GPL-3.0 license](LICENSE) and original
contributor attribution. The integrated TradeAPI source retains the original
Organic/nlog attribution and AGPL-3.0 license text in
[`THIRD_PARTY_LICENSES/TradeAPI-AGPL-3.0`](THIRD_PARTY_LICENSES/TradeAPI-AGPL-3.0).
The bundled resource copy is kept at
[`resources/TRADEAPI-AGPL-3.0`](resources/TRADEAPI-AGPL-3.0). The final license
and redistribution policy should be reviewed with the original rights
holders before publishing a combined release.
