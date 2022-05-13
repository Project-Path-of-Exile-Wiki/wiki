# Namespaces

# Add this to your LocalSettings.php file before running the import

```
// Define constants for my additional namespaces.
define("NS_BUFF", 10000); // This MUST be even.
define("NS_BUFF_TALK", 10001); // This MUST be the following odd integer.
define("NS_PASSIVE_SKILL", 10002); // This MUST be even.
define("NS_PASSIVE_SKILL_TALK", 10003);
define("NS_MODIFIER", 10004); // This MUST be even.
define("NS_MODIFIER_TALK", 10005);
define("NS_MONSTER", 10006); // This MUST be even.
define("NS_MONSTER_TALK", 10007);
define("NS_STAT", 10008); // This MUST be even.
define("NS_STAT_TALK", 10009);
define("NS_SKILL", 10010); // This MUST be even.
define("NS_SKILL_TALK", 10011);
define("NS_AREA", 10012); // This MUST be even.
define("NS_AREA_TALK", 10013);
define("NS_GUIDE", 10014); // This MUST be even.
define("NS_GUIDE_TALK", 10015);

// Add namespaces.
$wgExtraNamespaces[NS_BUFF] = "Buff";
$wgExtraNamespaces[NS_BUFF_TALK] = "Buff_talk"; // Note underscores in the namespace name.
$wgExtraNamespaces[NS_PASSIVE_SKILL] = "Passive_Skill";
$wgExtraNamespaces[NS_PASSIVE_SKILL_TALK] = "Passive_Skill_talk";
$wgExtraNamespaces[NS_MODIFIER] = "Modifier";
$wgExtraNamespaces[NS_MODIFIER_TALK] = "Modifier_talk";
$wgExtraNamespaces[NS_MONSTER] = "Monster";
$wgExtraNamespaces[NS_MONSTER_TALK] = "Monster_talk";
$wgExtraNamespaces[NS_STAT] = "Stat";
$wgExtraNamespaces[NS_STAT_TALK] = "Stat_talk";
$wgExtraNamespaces[NS_SKILL] = "Skill";
$wgExtraNamespaces[NS_SKILL_TALK] = "Skill_talk";
$wgExtraNamespaces[NS_AREA] = "Area";
$wgExtraNamespaces[NS_AREA_TALK] = "Area_talk";
$wgExtraNamespaces[NS_GUIDE] = "Guide";
$wgExtraNamespaces[NS_GUIDE_TALK] = "Guide_talk";

$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl = "https://creativecommons.org/licenses/by-nc-sa/3.0/";
$wgRightsText = "Creative Commons Attribution-NonCommercial 3.0 Unported";
$wgRightsIcon = "$wgScriptPath/resources/assets/licenses/cc-by-nc-sa.png";
```
