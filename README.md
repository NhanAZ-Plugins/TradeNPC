# TradeNPC source collection

This repository contains three nested historical source trees. Its root is not a PocketMine-MP plugin and has no root `plugin.yml`.

| Directory | Manifest API | Attribution and license in that directory |
|---|---|---|
| [`TradeNPC-main/`](TradeNPC-main/) | `5.0.0` | alvin0319 and DavidGlitch04, GPL-3.0 text |
| [`TradeNPC-Fixed-master/`](TradeNPC-Fixed-master/) | `4.0.0` | alvin0319 and DavidGlitch04, no local license file |
| [`TradeAPI-stable/`](TradeAPI-stable/) | `5.0.0` | nlog, AGPL-3.0 text |

The API 5 TradeNPC source imports InvMenu and SimplePacketHandler, but this collection does not pin their revisions or provide a root build workflow. TradeAPI is a separate plugin source tree; its presence here does not prove that either TradeNPC snapshot depends on it. Do not combine the three directories into one PHAR or infer that their licenses are interchangeable.

No PHPStan maximum level, DevTools PHAR, Axolotl-PM runtime smoke test or current release has been verified for these snapshots. A maintained distribution needs an explicit source choice, preserved upstream notices, dependency pins and tests on a disposable server before use with player trades.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
