<?php
declare(strict_types = 1);
require_once ('hhb_.inc.php');
require_once ('ForceUTF8.php');
$OTDataDir = 'C:\tibia\YurOTS\data';
$OTDataDir = str_replace ( '\\', '/', $OTDataDir );
hhb_init ();
requireCLI ();
$db = getDB ( $OTDataDir );
if (! chdir ( 'generatedHTML' )) {
	throw new RuntimeException ( 'failed to go to generatedHTML folder!' );
}

(function                          /*generateIndexHTML*/()use(&$db) {
	ob_start ();
	?>
<!DOCTYPE HTML>
<html>
<head>
<title>monster lib</title>
</head>
<body>
	<div>
		welcome to monster lib!<br />
	here you can read about <?php echo $db->query('SELECT COUNT(*) AS res FROM monsters')->fetch(PDO::FETCH_ASSOC)['res'];?> monsters and
	<?php echo $db->query('SELECT COUNT(*) AS res FROM `items`')->fetch(PDO::FETCH_ASSOC)['res'];?> items on record!
	<br /> <big><a href="monsters.html">to read about monsters, click here</a></big><br />
		<br /> <big><a href="items.html">to read about items, click here</a></big>
	</div>
	<br />
	<br />
	<small>monster lib version 0-dev, generated on <?php echo date(DATE_RFC2822);?></small>
</body>
</html>
<?php
	$html = ob_get_clean ();
	file_put_contents ( 'index.html', $html );
}) ();

$query = <<<'SQL'
SELECT * FROM monsters

SQL;
foreach ( $db->query ( $query, PDO::FETCH_ASSOC ) as $monster ) {
	var_dump ( $monster );
	die ();
}
function getDB($OTDataDir) {
	$db = new PDO ( /*'sqlite::memory:'*/'sqlite:monsterdb.sqlite3', '', '', array (
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_EMULATE_PREPARES => false 
	) );
	$db->exec ( 'PRAGMA synchronous = OFF' );
	$db->exec ( 'PRAGMA journal_mode = MEMORY' );
	$schema = <<<'SQLITESCHEMA'
DROP TABLE IF EXISTS `monsters`;
CREATE TABLE `monsters` (
		id INTEGER PRIMARY KEY AUTOINCREMENT NULL,
		name VARCHAR(255) UNIQUE,
		level INTEGER,
		maglevel INTEGER,
		experience INTEGER,
		pushable INTEGER,
		armor INTEGER,
		defense INTEGER,
		canpushitems BOOLEAN DEFAULT 0,
		staticattack INTEGER,
		changetarget INTEGER,
		speed INTEGER,
		health_max INTEGER,
		look_type INTEGER,
		look_head INTEGER,
		look_body INTEGER,
		look_legs INTEGER,
		look_feet INTEGER,
		look_corpse INTEGER,
		combat_targetdistance INTEGER,
		combat_runonhealth INTEGER,
		blood_color INTEGER,
		blood_effect INTEGER,
		blood_splash INTEGER,
		summon_cost INTEGER DEFAULT 0,
		thumbnail_url VARCHAR(255)
);

DROP TABLE IF EXISTS `monster_attacks`;
	CREATE TABLE `monster_attacks`(
			id INTEGER PRIMARY KEY AUTOINCREMENT NULL,
			monster_id INTEGER NOT NULL,
			type VARCHAR(255),
			name VARCHAR(255),
			mindamage INTEGER,
			maxdamage INTEGER,
			cycleticks INTEGER,
			probability INTEGER,
			exhaustion INTEGER
);
DROP TABLE IF EXISTS monster_defenses;
	CREATE TABLE `monster_defenses`(
			id INTEGER PRIMARY KEY AUTOINCREMENT NULL,
			monster_id INTEGER NOT NULL,
			immunity VARCHAR(255)

);
DROP TABLE IF EXISTS monster_voices;
	CREATE TABLE `monster_voices`(
			id INTEGER PRIMARY KEY AUTOINCREMENT NULL,
			monster_id INTEGER NOT NULL,
			sentence VARCHAR(255)
);

DROP TABLE IF EXISTS monster_loot;
	CREATE TABLE `monster_loot`(
			id INTEGER PRIMARY KEY AUTOINCREMENT NULL,
			monster_id INTEGER NOT NULL,
			item_id INTEGER NOT NULL,
			countmax INTEGER DEFAULT 1,
			chance1 INTEGER,
			chancemax INTEGER

);
	-- TODO: MONSTER SUMMONS.
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items`(
			id INTEGER PRIMARY KEY NOT NULL,
			`name` VARCHAR(255),
			`description` VARCHAR(255),
		thumbnail_url VARCHAR(255)
);
SQLITESCHEMA;
	
	$db->exec ( $schema );
	(function                                                                                  /*addItemsToDB*/($db) {
		
		/*
		 * FIXME: the OTB format is complex and poorly documented (at least the 7.6/OTServ 0.5.0 version of the format)
		 * so instead of implementing a full otb parser, i modified otserv to give me the php source code,
		 * it looks like this (and is far from optimal but whatever):
		 * for(int i=0;i<65535+1;++i){;
		 * if(i%1000==0){
		 * //std::cout << i << std::endl;
		 * }
		 * Item* it = Item::CreateItem(i,1);
		 * if(!it){
		 * std::cout << "failed to create item id " << i << std::endl;
		 * continue;
		 * }
		 * std::string description=it->getDescription(true);
		 * if(description.length()==0){
		 * description=it->getName();
		 * }
		 * if(description.length()==0){
		 * //std::cout << "skipping " << i;
		 * delete it;
		 * continue;
		 * }
		 * //std::cout << "created: " << description << std::endl;
		 * std::cout << "" << it->getID() << " => ['name' => '" << it->getName() << "', 'description'=>'" << description << "'],\n";
		 * delete it;
		 * }
		 * std::cout << "done..." << std::endl;;exit(EXIT_FAILURE);
		 */
		$stm = $db->prepare ( 'INSERT INTO `items` (id,name,description,thumbnail_url) VALUES(:id,:name,:description,:thumbnail_url);' );
		$insid;
		$insname;
		$insdescription;
		$insthumbnail_url;
		$stm->bindParam ( ':id', $insid );
		$stm->bindParam ( ':name', $insname );
		$stm->bindParam ( ':description', $insdescription );
		$stm->bindParam ( ':thumbnail_url', $insthumbnailurl );
		foreach ( json_decode ( file_get_contents ( 'items.json' ), true ) as $id => $data ) {
			$insid = $id;
			$insname = $data ['name'];
			$insdescription = $data ['description'];
			$insthumbnailurl = getImageThumbnailOfThing ( $data ['name'] );
			$stm->execute ();
		}
		unset ( $insid, $insname, $insdescription, $stm );
	}) ( $db );
	(function                                                                                 /*addMonstersToDB*/($db) use ($OTDataDir) {
		$summons = (function ($summonXML): array {
			$domd = @DOMDocument::loadHTML ( $summonXML );
			if (! $domd) {
				throw new RuntimeException ( 'unabel to load summons.xml' );
			}
			$ret = [ ];
			foreach ( $domd->getElementsByTagName ( "summon" ) as $summon ) {
				$ret [$summon->getAttribute ( 'name' )] = $summon->getAttribute ( 'mana' );
			}
			return $ret;
		}) ( $OTDataDir . '/summons.xml' );
		$monsterXMLs = glob ( $OTDataDir . '/monster/*.xml' );
		if (! is_array ( $monsterXMLs ) || count ( $monsterXMLs ) < 2) {
			throw new RuntimeException ( 'Unable to find monsters in ' . $OTDataDir . '/monster/' );
		}
		unset ( $monsterXMLs [array_search ( $OTDataDir . '/monster/monsters.xml', $monsterXMLs )] );
		$stm = $db->prepare ( 'INSERT INTO monsters
				(
		name,
		level,
		maglevel,
		experience,
		pushable,
		armor,
		defense,
		canpushitems,
		staticattack,
		changetarget,
		speed,
		health_max,
		look_type,
		look_head,
		look_body,
		look_legs,
		look_feet,
		look_corpse,
		combat_targetdistance,
		combat_runonhealth,
		blood_color,
		blood_effect,
		blood_splash,
		summon_cost,
		thumbnail_url
		) VALUES(
		:name,
		:level,
		:maglevel,
		:experience,
		:pushable,
		:armor,
		:defense,
		:canpushitems,
		:staticattack,
		:changetarget,
		:speed,
		:health_max,
		:look_type,
		:look_head,
		:look_body,
		:look_legs,
		:look_feet,
		:look_corpse,
		:combat_targetdistance,
		:combat_runonhealth,
		:blood_color,
		:blood_effect,
		:blood_splash,
		:summon_cost,
		:thumbnail_url
		);' );
		$stm->bindParam ( ':name', $ins_name );
		$stm->bindParam ( ':level', $ins_level );
		$stm->bindParam ( ':maglevel', $ins_maglevel );
		$stm->bindParam ( ':experience', $ins_experience );
		$stm->bindParam ( ':pushable', $ins_pushable );
		$stm->bindParam ( ':armor', $ins_armor );
		$stm->bindParam ( ':defense', $ins_defense );
		$stm->bindParam ( ':canpushitems', $ins_canpushitems );
		$stm->bindParam ( ':staticattack', $ins_staticattack );
		$stm->bindParam ( ':changetarget', $ins_changetarget );
		$stm->bindParam ( ':speed', $ins_speed );
		$stm->bindParam ( ':health_max', $ins_health_max );
		$stm->bindParam ( ':look_type', $ins_look_type );
		$stm->bindParam ( ':look_head', $ins_look_head );
		$stm->bindParam ( ':look_body', $ins_look_body );
		$stm->bindParam ( ':look_legs', $ins_look_legs );
		$stm->bindParam ( ':look_feet', $ins_look_feet );
		$stm->bindParam ( ':look_corpse', $ins_look_corpse );
		$stm->bindParam ( ':combat_targetdistance', $ins_combat_targetdistance );
		$stm->bindParam ( ':combat_runonhealth', $ins_combat_runonhealth );
		$stm->bindParam ( ':blood_color', $ins_blood_color );
		$stm->bindParam ( ':blood_effect', $ins_blood_effect );
		$stm->bindParam ( ':blood_splash', $ins_blood_splash );
		$stm->bindParam ( ':summon_cost', $ins_summon_cost );
		$stm->bindParam ( ':thumbnail_url', $ins_thumbnail_url );
		$stm_loot = $db->prepare ( 'INSERT INTO monster_loot (monster_id,item_id,countmax,chance1,chancemax) VALUES(:monster_id,:item_id,:countmax,:chance1,:chancemax);' );
		$stm_loot->bindParam ( ':monster_id', $loot_ins_monster_id );
		$stm_loot->bindParam ( ':item_id', $loot_ins_item_id );
		$stm_loot->bindParam ( ':countmax', $loot_ins_countmax );
		$stm_loot->bindParam ( ':chance1', $loot_ins_chance1 );
		$stm_loot->bindParam ( ':chancemax', $loot_ins_chancemax );
		foreach ( $monsterXMLs as $monsterXML ) {
			echo "processing " . $monsterXML . PHP_EOL;
			$monsterDOMD = @DOMDocument::loadHTMLFile ( $monsterXML );
			if (! $monsterDOMD) {
				throw new RuntimeException ( 'Failed to load monster file ' . $monsterXML );
			}
			
			$monster = $monsterDOMD->getElementsByTagName ( "monster" )->item ( 0 );
			$ins_name = $monster->getAttribute ( "name" );
			if (strlen ( $ins_name ) < 1) {
				throw new RuntimeException ( 'invalid name found in monster XML ' . $monsterXML );
			}
			$ins_level = $monster->getAttribute ( 'level' );
			$ins_maglevel = $monster->getAttribute ( 'maglevel' );
			$ins_experience = $monster->getAttribute ( "experience" );
			$ins_pushable = $monster->getAttribute ( "pushable" );
			$ins_armor = $monster->getAttribute ( "armor" );
			$ins_defense = $monster->getAttribute ( "defense" );
			$ins_canpushitems = $monster->getAttribute ( "canpushitems" );
			$ins_staticattack = $monster->getAttribute ( "staticattack" );
			$ins_changetarget = $monster->getAttribute ( "changetarget" );
			$ins_speed = $monster->getAttribute ( "speed" );
			if ($monsterDOMD->getElementsByTagName ( "health" )->item ( 0 )) {
				$ins_health_max = $monsterDOMD->getElementsByTagName ( "health" )->item ( 0 )->getAttribute ( 'max' );
			} else {
				$ins_health_max = '0';
			}
			$look = $monsterDOMD->getElementsByTagName ( "look" )->item ( 0 );
			$ins_look_type = $look->getAttribute ( 'type' );
			$ins_look_head = $look->getAttribute ( 'head' );
			$ins_look_body = $look->getAttribute ( 'body' );
			$ins_look_legs = $look->getAttribute ( 'legs' );
			$ins_look_feet = $look->getAttribute ( 'feet' );
			$ins_look_corpse = $look->getAttribute ( 'corpse' );
			$ins_combat_targetdistance = $monsterDOMD->getElementsByTagName ( "combat" )->item ( 0 )->getAttribute ( 'targetdistance' );
			$ins_combat_runonhealth = $monsterDOMD->getElementsByTagName ( "combat" )->item ( 0 )->getAttribute ( 'runonhealth' );
			$blood = $monsterDOMD->getElementsByTagName ( 'blood' )->item ( 0 );
			
			$ins_blood_color = ! $blood ? 0 : $blood->getAttribute ( 'color' );
			$ins_blood_effect = ! $blood ? 0 : $blood->getAttribute ( 'effect' );
			$ins_blood_splash = ! $blood ? 0 : $blood->getAttribute ( 'splash' );
			$ins_summon_cost = $summons [$ins_name] ?? 0; // 0 means can't be summoned. :p
			$ins_thumbnail_url = getImageThumbnailOfThing ( $ins_name );
			$stm->execute ();
			$monster_id = $db->lastInsertId ();
			
			$loot_ins_monster_id = $monster_id;
			foreach ( $monsterDOMD->getElementsByTagName ( "item" ) as $loot ) {
				$loot_ins_item_id = $loot->getAttribute ( 'id' );
				$loot_ins_countmax = $loot->getAttribute ( 'countmax' );
				if (! $loot_ins_countmax) {
					$loot_ins_countmax = 1;
				}
				if ($loot->hasAttribute ( 'chance1' )) {
					$loot_ins_chance1 = $loot->getAttribute ( 'chance1' );
				} elseif ($loot->hasAttribute ( 'chance' )) {
					$loot_ins_chance1 = $loot->getAttribute ( 'chance' );
				} else {
					$loot_ins_chance1 = '0'; // dunno lol, some hardcoded value in the engine probably.
				}
				if ($loot->hasAttribute ( 'chancemax' )) {
					$loot_ins_chancemax = $loot->hasAttribute ( 'chancemax' );
				} else {
					$loot_ins_chancemax = '0'; // ?
				}
				$stm_loot->execute ();
			}
		}
	}) ( $db );
	
	return $db;
}
function requireCLI() {
	if (php_sapi_name () !== 'cli') {
		die ( 'this script is meant to be run from command line only..' );
	}
}
function getImageThumbnailOfThing(string $thing): string {
	// Optimally we should parse the tibia.spr instead of this shit.. :/
	// $thing=ForceUTF8\Encoding::toUTF8($thing);
	static $cache = false; // yeah its worth it.
	$save = function () use (&$cache) {
		
		// $starttime = microtime ( true );
		$json = json_encode ( $cache, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if (false === $json) {
			throw new RuntimeException ( 'json_encode fail: ' . var_export ( json_last_error (), true ) . ": " . var_export ( json_last_error_msg (), true ) );
		}
		$json = str_replace ( '\u0026', '&', $json ); // annoying enough to warrant even a hacky fix..
		file_put_contents ( 'ImageThumbnailURLCache.json', $json );
		// echo "used " . (microtime ( true ) - $starttime) . PHP_EOL;
	};
	if ($cache === false) {
		if (is_readable ( 'ImageThumbnailURLCache.json' ) && filesize ( 'ImageThumbnailURLCache.json' ) > 1) {
			$cache = json_decode ( file_get_contents ( 'ImageThumbnailURLCache.json' ), true );
		} else {
			$cache = array ();
			echo "Rebuilding initial thumnail url cache.." . PHP_EOL;
			(function () use (&$cache, &$save) {
				$hc = new hhb_curl ();
				$hc->_setComfortableOptions ();
				$i = 0;
				while ( true ) {
					++ $i;
					$url = 'http://tibia.wikia.com/wiki/Special:Images?page=' . urlencode ( ( string ) $i );
					$hc->exec ( $url );
					$domd = @DOMDocument::loadHTML ( $hc->getResponseBody () );
					$main = $domd->getElementById ( "mw-content-text" );
					if (! $main) {
						break;
					}
					$images = $main->getElementsByTagName ( "img" );
					if ($images->length < 1) {
						break;
					}
					foreach ( $images as $img ) {
						$name = mb_strtolower ( $img->getAttribute ( "alt" ), 'UTF-8' );
						if (isset ( $cache [$name] )) {
							continue;
						}
						$url = $img->getAttribute ( "src" );
						if (0 === stripos ( $url, 'data:' )) {
							continue;
						}
						
						$cache [$name] = $url;
						echo "added " . $name . PHP_EOL;
						if (false !== strpos ( $name, ' corpse' )) {
							$name = 'dead ' . substr ( $name, 0, - (strlen ( ' corpse' )) );
							$cache [$name] = $url;
							echo 'added ' . $name . PHP_EOL;
						}
					}
					$save ();
				}
			}) ();
			echo "done" . PHP_EOL;
		}
	}
	$internalGetImageThumbnailOfThing = function ($thing) {
		$hc = new hhb_curl ();
		$hc->_setComfortableOptions ();
		$url = 'http://tibia.wikia.com/wiki/' . urldecode ( str_replace ( ' ', '_', $thing ) );
		$hc->exec ( $url );
		$html = $hc->getResponseBody ();
		// hhb_var_dump ( $html, $hc->getResponsesHeaders (), $hc->getStdErr () );
		// die ();
		$domd = @DOMDocument::loadHTML ( $html );
		$name = $thing;
		if (false !== stripos ( $domd->getElementById ( "WikiaPageHeader" )->textContent, 'Redirected from' )) {
			$name = trim ( $domd->getElementById ( "WikiaPageHeader" )->getElementsByTagName ( "h1" )->item ( 0 )->textContent );
		}
		
		foreach ( $domd->getElementsByTagName ( "*" ) as $ele ) {
			if (strcasecmp ( $ele->getAttribute ( "alt" ), $name ) !== 0) { // FIXME: UTF8 strcasecmp
				continue;
			}
			// echo "found it!";
			return $ele->getAttribute ( "src" );
		}
		unset ( $ele, $name );
		
		// failed... look for a "did you mean?" suggestion..
		foreach ( $domd->getElementsByTagName ( "b" ) as $ele ) {
			if (false === stripos ( $ele->textContent, 'Did you mean' )) {
				continue;
			}
			// echo "found it! (wrong spelling with suggestion. probably a cAsE Error)";
			// hhb_var_dump ( "FOUND SUGGESTION", $ele, $domd->saveHTML ( $ele ), 'http://tibia.wikia.com' . GetElementsByTagNameDOMNodeHack ( $ele, 'a', $html ) [0]->getAttribute ( "href" ) );
			// die ();
			$suggestionURL = 'http://tibia.wikia.com' . GetElementsByTagNameDOMNodeHack ( $ele, 'a' ) [0]->getAttribute ( "href" );
			$hc->exec ( $suggestionURL );
			$domd2 = @DOMDocument::loadHTML ( $hc->getResponseBody () );
			$image = $domd2->getElementById ( "twbox-image" );
			if (! $image) {
				continue; //
			}
			$image = $image->getElementsByTagName ( "a" );
			if ($image->length < 1) {
				continue;
			}
			$image = $image->item ( 0 )->getAttribute ( "href" );
			if (! $image) {
				continue;
			}
			return $image;
		}
		return false;
	};
	
	if (isset ( $cache [mb_strtolower ( $thing, 'UTF-8' )] )) {
		return $cache [mb_strtolower ( $thing, 'UTF-8' )];
	}
	echo "getting thumbnail url for \"" . $thing . "\": ";
	$url = $internalGetImageThumbnailOfThing ( $thing );
	
	if ($url === false) {
		$url = $internalGetImageThumbnailOfThing ( $thing . ' (Tile)' );
	}
	if ($url === false) {
		$url = $internalGetImageThumbnailOfThing ( $thing . ' (Item)' ); // like Skull
	}
	if ($url === false) {
		$url = '';
		echo "failed." . PHP_EOL;
	} else {
		echo "found." . PHP_EOL;
	}
	$cache [mb_strtolower ( $thing, 'UTF-8' )] = $url;
	$save ();
	return $url;
}
function GetElementsByTagNameDOMNodeHack(DOMNode $domnode, string $name): array {
	$ret = array ();
	foreach ( ($domnode->childNodes ?? [ ]) as $add ) {
		if ($add->nodeName === $name) {
			$ret [] = $add;
		}
		// possibly a bug in PHP 7.0.3, contrary to documentation,
		// childNodes CAN be NULL sometimes (the documentation says it should *always* be a DOMNodeList, even if
		// an empty DOMNodeList. then foreach() started complaining that i was giving it NULLs!!
		// just wokring around it for now...
		// var_dump ( $add->childNodes );
		// if ($add->childNodes === null) {
		// var_dump ( 'childNodes is null again!', $html );
		// die ();
		// }
		foreach ( ($add->childNodes ?? [ ]) as $recur ) {
			$ret = array_merge ( $ret, GetElementsByTagNameDOMNodeHack ( $recur, $name ) );
		}
	}
	return $ret;
}
function GetElementsByAttributeValue(/*DOMNode|DOMDocument*/ $domnode, string $attribute, string $value): array {
	if (is_a ( $domnode, 'DOMNode' )) {
		$ret = [ ];
		foreach ( ($domnode->childNodes ?? [ ]) as $add ) {
			if (! method_exists ( $add, 'getAttribute' )) {
				continue; // not all nodes have getAttribute, apparently...
			}
			if (false !== stripos ( $add->getAttribute ( $attribute ), $value )) {
				$ret [] = $add;
			}
			// if ($add->getAttribute ( $attribute ))
			// var_dump ( $add->getAttribute ( $attribute ) );
			foreach ( ($add->childNodes ?? [ ]) as $recur ) {
				$ret = array_merge ( $ret, GetElementsByAttributeValue ( $recur, $attribute, $value ) );
			}
		}
		return $ret;
	} elseif (is_a ( $domnode, 'DOMDocument' )) {
		$ret = [ ];
		foreach ( $domnode->getElementsByTagName ( '*' ) as $add ) {
			if (! method_exists ( $add, 'getAttribute' )) {
				continue; // not all nodes have getAttribute, apparently...
			}
			if (false !== stripos ( $add->getAttribute ( $attribute ), $value )) {
				$ret [] = $add;
			}
			// if ($add->getAttribute ( $attribute ))
			// var_dump ( $add->getAttribute ( $attribute ) );
		}
		return $ret;
	} else {
		throw new InvalidArgumentException ( 'argument 1 MUST be a DOMDocument or a DOMNode, but ' . (gettype ( $domnode ) === 'object' ? get_class ( $domnode ) : gettype ( $domnode )) . ' provided!' );
	}
}