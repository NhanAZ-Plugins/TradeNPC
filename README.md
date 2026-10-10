# TradeNPC source collection

This repository contains three nested historical source trees. Its root is not a PocketMine-MP plugin and has no root `plugin.yml`.

| Directory | Manifest API | Attribution and license in that directory |
|---|---|---|
| [`TradeNPC-main/`](TradeNPC-main/) | `5.0.0` | alvin0319 and DavidGlitch04, GPL-3.0 text |
| [`TradeNPC-Fixed-master/`](TradeNPC-Fixed-master/) | `4.0.0` | alvin0319 and DavidGlitch04, no local license file |
| [`TradeAPI-stable/`](TradeAPI-stable/) | `5.0.0` | nlog, AGPL-3.0 text |

The [`TradeNPC-main/`](TradeNPC-main/) source uses [InvMenu](https://github.com/Muqsit/InvMenu), pinned to its compatible 4.6.1 source in the [build workflow](.github/workflows/build.yml). Its `plugin.yml` declares API 5.0.0. The workflow checks PHPStan at maximum level against pinned Axolotl-PM 5.0.0 and 5.49.1 source revisions and builds a PHAR with [DevTools](https://github.com/NhanAZ/DevTools). These checks establish compatibility with the tested revisions, not every Axolotl-PM 5.x release. TradeAPI is a separate plugin source tree; its presence here does not prove that either TradeNPC snapshot depends on it. Do not combine the three directories into one PHAR or infer that their licenses are interchangeable.

The TradeNPC packet listener ignores pre-login trade packets, invalid recipe indices and incomplete recipes. It checks the combined trade cost and reward capacity in a temporary inventory before changing the player's inventory, leaves normal inventory actions to server validation, and accepts only complete recipe input rows. The [packet guard](TradeNPC-main/tests/packet-guard.php), [trade settlement](TradeNPC-main/tests/trade-settlement.php), [recipe input](TradeNPC-main/tests/recipe-input.php) and [shop NBT](TradeNPC-main/tests/shop-nbt.php) fixtures use `AXOLOTL_AUTOLOAD` to select a pinned server Composer autoload file. A local PHAR built from the selected source and dependency started and stopped successfully on the Axolotl-PM 5.49.1 release server. Connected-client trading and the complete transaction flow remain unverified. The separate TradeAPI and API 4 snapshots still require their own analysis and build decisions; no current release of this source collection is verified.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
