# TradeNPC

TradeNPC is an Axolotl-PM plugin for creating named trading NPCs with `/npc create`, configuring offers with `/npc setitem`, and removing a saved NPC with `/npc remove`. The active source is the API 5 port in this directory; the original API 4 snapshot is retained separately and is not built.

## Compatibility

The plugin manifest declares API `5.0.0`. CI runs PHPStan at maximum level and builds the PHAR against pinned Axolotl-PM 5.0.0 and 5.49.1 source revisions. The PHAR was started on the 5.49.1 release server, and a headless client completed one trade there. These tested revisions do not establish compatibility with every 5.x release or verify the in-game Bedrock trading screen.

## Trade safety

The trade handler validates the selected offer, both required inputs, and room for the output before settling a trade. Incomplete offers and pre-login packets are ignored. Normal inventory actions remain subject to server validation. Regression fixtures cover packet guards, complete recipe inputs, settlement, and persisted NPC offers.

## Build

The repository workflow uses [DevTools](https://github.com/NhanAZ/DevTools) and a pinned [InvMenu](https://github.com/Muqsit/InvMenu) source revision to analyze the source, build the plugin PHAR, and verify its contents and checksum. Review the [build workflow](../.github/workflows/build.yml) for the exact tested server commits and run evidence.

## License

This active API 5 source retains its [GPL-3.0 license](LICENSE) and original contributor attribution. The separate API 4 source snapshot and `TradeAPI-stable` project have different or unresolved license terms; they are not included in this plugin PHAR and the root collection is not published as a stable release artifact.
