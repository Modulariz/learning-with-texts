<?php

/**************************************************************
"Learning with Texts" (LWT) is free and unencumbered software 
released into the PUBLIC DOMAIN.

Anyone is free to copy, modify, publish, use, compile, sell, or
distribute this software, either in source code form or as a
compiled binary, for any purpose, commercial or non-commercial,
and by any means.

In jurisdictions that recognize copyright laws, the author or
authors of this software dedicate any and all copyright
interest in the software to the public domain. We make this
dedication for the benefit of the public at large and to the 
detriment of our heirs and successors. We intend this 
dedication to be an overt act of relinquishment in perpetuity
of all present and future rights to this software under
copyright law.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE 
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS BE LIABLE 
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN 
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN 
THE SOFTWARE.

For more information, please refer to [http://unlicense.org/].
***************************************************************/

/**************************************************************
Call: edit_words.php?....
      ... markaction=[opcode] ... do actions on marked terms
      ... allaction=[opcode] ... do actions on all terms
      ... del=[wordid] ... do delete
      ... op=Save ... do insert new 
      ... op=Change ... do update
      ... new=1&lang=[langid] ... display new term screen 
      ... chg=[wordid] ... display edit screen 
      ... filterlang=[langid] ... language filter 
      ... sort=[sortcode] ... sort 
      ... page=[pageno] ... page  
      ... query=[termtextfilter] ... term text filter   
      ... status=[statuscode] ... status filter   
      ... text=[textid] ... text filter   
      ... tag1=[tagid] ... tag filter 1   
      ... tag2=[tagid] ... tag filter 2   
      ... tag12=0/1 ... tag1-tag2 OR=0, AND=1   
Manage terms
***************************************************************/

require_once( 'settings.inc.php' );
require_once( 'connect.inc.php' );
require_once( 'dbutils.inc.php' );
require_once( 'utilities.inc.php' );
require_once( 'simterms.inc.php' );

$currentlang = validateLang(processDBParam("filterlang",'currentlanguage','',0));
$currentsort = processDBParam("sort",'currentwordsort','1',1);

$currentpage = processSessParam("page","currentwordpage",'1',1);
$currentquery = processSessParam("query","currentwordquery",'',0);
$currentstatus = processSessParam("status","currentwordstatus",'',0);
$currenttext = validateText(processSessParam("text","currentwordtext",'',0));
$currenttag1 = validateTag(processSessParam("tag1","currentwordtag1",'',0),$currentlang);
$currenttag2 = validateTag(processSessParam("tag2","currentwordtag2",'',0),$currentlang);
$currenttag12 = processSessParam("tag12","currentwordtag12",'',0);

$wh_lang = ($currentlang != '') ? (' and WoLgID=' . $currentlang ) : '';
$wh_stat = ($currentstatus != '') ? (' and ' . makeStatusCondition('WoStatus', $currentstatus)) : '';
$wh_query = convert_string_to_sqlsyntax(str_replace("*","%",mb_strtolower($currentquery, 'UTF-8')));
$wh_query = ($currentquery != '') ? (' and (WoText like ' . $wh_query . ' or WoRomanization like ' . $wh_query . ' or WoTranslation like ' . $wh_query . ')') : '';

if ($currenttag1 == '' && $currenttag2 == '')
	$wh_tag = '';
else {
	if ($currenttag1 != '') {
		if ($currenttag1 == -1)
			$wh_tag1 = "group_concat(WtTgID) IS NULL";
		else
			$wh_tag1 = "concat('/',group_concat(WtTgID separator '/'),'/') like '%/" . $currenttag1 . "/%'";
	} 
	if ($currenttag2 != '') {
		if ($currenttag2 == -1)
			$wh_tag2 = "group_concat(WtTgID) IS NULL";
		else
			$wh_tag2 = "concat('/',group_concat(WtTgID separator '/'),'/') like '%/" . $currenttag2 . "/%'";
	} 
	if ($currenttag1 != '' && $currenttag2 == '')	
		$wh_tag = " having (" . $wh_tag1 . ') ';
	elseif ($currenttag2 != '' && $currenttag1 == '')	
		$wh_tag = " having (" . $wh_tag2 . ') ';
	else
		$wh_tag = " having ((" . $wh_tag1 . ($currenttag12 ? ') AND (' : ') OR (') . $wh_tag2 . ')) ';
}

$no_pagestart = 
	(getreq('markaction') == 'exp') ||
	(getreq('markaction') == 'exp2') ||
	(getreq('markaction') == 'exp3') ||
	(getreq('markaction') == 'test') ||
	(getreq('markaction') == 'deltag') ||
	(getreq('allaction') == 'expall') ||
	(getreq('allaction') == 'expall2') ||
	(getreq('allaction') == 'expall3') ||
	(getreq('allaction') == 'testall') ||
	(getreq('allaction') == 'deltagall');
if (! $no_pagestart) {
	pagestart('My ' . getLanguage($currentlang) . ' Terms (Words and Expressions)',true);
}

$message = '';

// MARK ACTIONS

if (isset($_REQUEST['markaction'])) {
	$markaction = $_REQUEST['markaction'];
	$actiondata = stripTheSlashesIfNeeded(getreq('data'));
	$message = "Multiple Actions: 0";
	if (isset($_REQUEST['marked'])) {
		if (is_array($_REQUEST['marked'])) {
			$l = count($_REQUEST['marked']);
			if ($l > 0 ) {
				$list = "(" . $_REQUEST['marked'][0];
				for ($i=1; $i<$l; $i++) $list .= "," . $_REQUEST['marked'][$i];
				$list .= ")";
				if ($markaction == 'del') {
					$message = runsql('delete from ' . $tbpref . 'words where WoID in ' . $list, "Deleted");
					adjust_autoincr('words','WoID');
					runsql("DELETE " . $tbpref . "wordtags FROM (" . $tbpref . "wordtags LEFT JOIN " . $tbpref . "words on WtWoID = WoID) WHERE WoID IS NULL",'');
				}
				elseif ($markaction == 'addtag' ) {
					$message = addtaglist($actiondata,$list);
				}
				elseif ($markaction == 'deltag' ) {
					$message = removetaglist($actiondata,$list);
					header("Location: ".$_ENV['BASE_URL']."edit_words.php");
					exit();
				}
				elseif ($markaction == 'spl1' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatus=WoStatus+1, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoStatus in (1,2,3,4) and WoID in ' . $list, "Updated Status (+1)");
				}
				elseif ($markaction == 'smi1' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatus=WoStatus-1, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoStatus in (2,3,4,5) and WoID in ' . $list, "Updated Status (-1)");
				}
				elseif ($markaction == 's5' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatus=5, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID in ' . $list, "Updated Status (=5)");
				}
				elseif ($markaction == 's1' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatus=1, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID in ' . $list, "Updated Status (=1)");
				}
				elseif ($markaction == 's99' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatus=99, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID in ' . $list, "Updated Status (=99)");
				}
				elseif ($markaction == 's98' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatus=98, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID in ' . $list, "Updated Status (=98)");
				}
				elseif ($markaction == 'today' ) {
					$message = runsql('update ' . $tbpref . 'words set WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID in ' . $list, "Updated Status Date (= Now)");
				}
				elseif ($markaction == 'delsent' ) {
					$message = runsql('update ' . $tbpref . 'words set WoSentence = NULL where WoID in ' . $list, "Term Sentence(s) deleted");
				}
				elseif ($markaction == 'lower' ) {
					$message = runsql('update ' . $tbpref . 'words set WoText = WoTextLC where WoID in ' . $list, "Term(s) set to lowercase");
				}
				elseif ($markaction == 'cap' ) {
					$message = runsql('update ' . $tbpref . 'words set WoText = CONCAT(UPPER(LEFT(WoTextLC,1)),SUBSTRING(WoTextLC,2)) where WoID in ' . $list, "Term(s) capitalized");
				}
				elseif ($markaction == 'exp' ) {
					anki_export('select distinct WoID, LgRightToLeft, LgRegexpWordCharacters, LgName, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID AND WoTranslation != \'\' AND WoTranslation != \'*\' and WoSentence like concat(\'%{\',WoText,\'}%\') and WoID in ' . $list . ' group by WoID');
				}
				elseif ($markaction == 'exp2' ) {
					tsv_export('select distinct WoID, LgName, WoText, WoTranslation, WoRomanization, WoSentence, WoStatus, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID and WoID in ' . $list . ' group by WoID');
				}
				elseif ($markaction == 'exp3' ) {
					flexible_export('select distinct WoID, LgName, LgExportTemplate, LgRightToLeft, WoText, WoTextLC, WoTranslation, WoRomanization, WoSentence, WoStatus, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID and WoID in ' . $list . ' group by WoID');
				}
				elseif ($markaction == 'test' ) {
					$_SESSION['testsql'] = ' ' . $tbpref . 'words where WoID in ' . $list . ' ';
					header("Location: ".$_ENV['BASE_URL']."do_test.php?selection=1");
					exit();
				}
			}
		}
	}
}


// ALL ACTIONS 

if (isset($_REQUEST['allaction'])) {
	$allaction = $_REQUEST['allaction'];
	$actiondata = stripTheSlashesIfNeeded(getreq('data'));
	if ($allaction == 'delall' || $allaction == 'spl1all' || $allaction == 'smi1all' || $allaction == 's5all' || $allaction == 's1all' || $allaction == 's99all' || $allaction == 's98all' || $allaction == 'todayall' || $allaction == 'addtagall' || $allaction == 'deltagall' || $allaction == 'delsentall' || $allaction == 'lowerall' || $allaction == 'capall') {
		if ($currenttext == '') {
			$sql = 'select distinct WoID from (' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) where (1=1) ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag;
		} else {
			$sql = 'select distinct WoID from (' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID), ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag;
		}
		$cnt=0;
		$res = do_mysqli_query($sql);
		while ($record = mysqli_fetch_assoc($res)) {
			$id = $record['WoID'];
			$message='0';
			if ($allaction == 'delall' ) {
				$message = runsql('delete from ' . $tbpref . 'words where WoID = ' . $id, "");
			}
			elseif ($allaction == 'addtagall' ) {
				addtaglist($actiondata,'(' . $id . ')');
				$message = 1;
			}
			elseif ($allaction == 'deltagall' ) {
				removetaglist($actiondata,'(' . $id . ')');
				$message = 1;
			}
			elseif ($allaction == 'spl1all' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatus=WoStatus+1, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoStatus in (1,2,3,4) and WoID = ' . $id, "");
			}
			elseif ($allaction == 'smi1all' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatus=WoStatus-1, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoStatus in (2,3,4,5) and WoID = ' . $id, "");
			}
			elseif ($allaction == 's5all' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatus=5, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID = ' . $id, "");
			}
			elseif ($allaction == 's1all' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatus=1, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID = ' . $id, "");
			}
			elseif ($allaction == 's99all' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatus=99, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID = ' . $id, "");
			}
			elseif ($allaction == 's98all' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatus=98, WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID = ' . $id, "");
			}
			elseif ($allaction == 'todayall' ) {
				$message = runsql('update ' . $tbpref . 'words set WoStatusChanged = NOW(),' . make_score_random_insert_update('u') . ' where WoID = ' . $id, "");
			}
			elseif ($allaction == 'delsentall' ) {
				$message = runsql('update ' . $tbpref . 'words set WoSentence = NULL where WoID = ' . $id, "");
			}
			elseif ($allaction == 'lowerall' ) {
				$message = runsql('update ' . $tbpref . 'words set WoText = WoTextLC where WoID = ' . $id, "");
			}
			elseif ($allaction == 'capall' ) {
				$message = runsql('update ' . $tbpref . 'words set WoText = CONCAT(UPPER(LEFT(WoTextLC,1)),SUBSTRING(WoTextLC,2)) where WoID = ' . $id, "");
			}
			$cnt += (int)$message;
		}
		mysqli_free_result($res);
		if ($allaction == 'deltagall') {
			header("Location: ".$_ENV['BASE_URL']."edit_words.php");
			exit();
		}
		if ($allaction == 'addtagall') {
			$message = "Tag added in $cnt Terms";
		} else if ($allaction == 'delall') {
			$message = "Deleted: $cnt Terms";
			adjust_autoincr('words','WoID');
			runsql("DELETE " . $tbpref . "wordtags FROM (" . $tbpref . "wordtags LEFT JOIN " . $tbpref . "words on WtWoID = WoID) WHERE WoID IS NULL",'');
		}	else {
			$message = "$cnt Terms changed";
		}
	}

	elseif ($allaction == 'expall' ) {
		if ($currenttext == '') {
			anki_export('select distinct WoID, LgRightToLeft, LgRegexpWordCharacters, LgName, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID AND WoTranslation != \'*\' and WoSentence like concat(\'%{\',WoText,\'}%\') ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag);
		} else {
			anki_export('select distinct WoID, LgRightToLeft, LgRegexpWordCharacters, LgName, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages, ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . ' and WoLgID = LgID AND WoTranslation != \'*\' and WoSentence like concat(\'%{\',WoText,\'}%\') ' . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag);
		}
	}
	
	elseif ($allaction == 'expall2' ) {
		if ($currenttext == '') {
			tsv_export('select distinct WoID, LgName, WoText, WoTranslation, WoRomanization, WoSentence, WoStatus, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag);
		} else {
			tsv_export('select distinct WoID, LgName, WoText, WoTranslation, WoRomanization, WoSentence, WoStatus, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages, ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . ' and WoLgID = LgID ' . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag);
		}
	}
	
	elseif ($allaction == 'expall3' ) {
		if ($currenttext == '') {
			flexible_export('select distinct WoID, LgName, LgExportTemplate, LgRightToLeft, WoText, WoTextLC, WoTranslation, WoRomanization, WoSentence, WoStatus, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag);
		} else {
			flexible_export('select distinct WoID, LgName, LgExportTemplate, LgRightToLeft, WoText, WoTextLC, WoTranslation, WoRomanization, WoSentence, WoStatus, ifnull(group_concat(distinct TgText order by TgText separator \' \'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages, ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . ' and WoLgID = LgID ' . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag);
		}
	}
	
	elseif ($allaction == 'testall' ) {
		if ($currenttext == '') {
			$sql = 'select distinct WoID from (' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) where (1=1) ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag;
		} else {
			$sql = 'select distinct WoID from (' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID), ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag;
		}
		$cnt = 0;
		$list = '(';
		$res = do_mysqli_query($sql);
		while ($record = mysqli_fetch_assoc($res)) {
			$cnt++;
			$id = $record['WoID'];
			$list .= ($cnt==1 ? '' : ',') . $id;
		}	
		$list .= ")";
		mysqli_free_result($res);
		$_SESSION['testsql'] = ' ' . $tbpref . 'words where WoID in ' . $list . ' ';
		header("Location: ".$_ENV['BASE_URL']."do_test.php?selection=1");
		exit();
	}

}

// DEL

elseif (isset($_REQUEST['del'])) {
	$message = runsql('delete from ' . $tbpref . 'words where WoID = ' . $_REQUEST['del'], "Deleted");
	adjust_autoincr('words','WoID');
	runsql("DELETE " . $tbpref . "wordtags FROM (" . $tbpref . "wordtags LEFT JOIN " . $tbpref . "words on WtWoID = WoID) WHERE WoID IS NULL",'');
}

// INS/UPD

elseif (isset($_REQUEST['op'])) {

	$translation_raw = repl_tab_nl(getreq("WoTranslation"));
	if ( $translation_raw == '' ) $translation = '*';
	else $translation = $translation_raw;
	
	// INSERT
	
	if ($_REQUEST['op'] == 'Save') {
	
		$message = runsql('insert into ' . $tbpref . 'words (WoLgID, WoTextLC, WoText, ' .
			'WoStatus, WoTranslation, WoSentence, WoRomanization, WoStatusChanged,' .  make_score_random_insert_update('iv') . ') values( ' . 
			$_REQUEST["WoLgID"] . ', ' .
			convert_string_to_sqlsyntax(mb_strtolower($_REQUEST["WoText"], 'UTF-8')) . ', ' .
			convert_string_to_sqlsyntax($_REQUEST["WoText"]) . ', ' .
			$_REQUEST["WoStatus"] . ', ' .
			convert_string_to_sqlsyntax($translation) . ', ' .
			convert_string_to_sqlsyntax(repl_tab_nl($_REQUEST["WoSentence"])) . ', ' .
			convert_string_to_sqlsyntax($_REQUEST["WoRomanization"]) . ', NOW(), ' .  
make_score_random_insert_update('id') . ')', "Saved", $sqlerrdie = FALSE);
		$wid = get_last_key();
	}	
	
	// UPDATE
	
	else {

		$oldstatus = $_REQUEST["WoOldStatus"];
		$newstatus = $_REQUEST["WoStatus"];
		$xx = '';
		if ($oldstatus != $newstatus) $xx = ', WoStatus = ' .	$newstatus . ', WoStatusChanged = NOW()';
		$wid = $_REQUEST["WoID"] + 0;
		$message = runsql('update ' . $tbpref . 'words set WoText = ' . 
			convert_string_to_sqlsyntax($_REQUEST["WoText"]) . ', WoTextLC = ' . 
			convert_string_to_sqlsyntax(mb_strtolower($_REQUEST["WoText"], 'UTF-8')) . ', WoTranslation = ' . 
			convert_string_to_sqlsyntax($translation) . ', WoSentence = ' . 
			convert_string_to_sqlsyntax(repl_tab_nl($_REQUEST["WoSentence"])) . ', WoRomanization = ' .
			convert_string_to_sqlsyntax($_REQUEST["WoRomanization"]) . $xx . ',' . make_score_random_insert_update('u') . ' where WoID = ' . $_REQUEST["WoID"],
			"Updated", $sqlerrdie = FALSE);
	}
	
	saveWordTags($wid);

}

// NEW

if (isset($_REQUEST['new']) && isset($_REQUEST['lang'])) {

	$scrdir = getScriptDirectionTag($_REQUEST['lang']);
	
	?>

	<h4>New Term</h4>
	<script type="text/javascript" src="<?php echo $_ENV['BASE_URL']; ?>/js/unloadformcheck.js" charset="utf-8"></script>	
	<form name="newword" class="validate" action="<?php echo $_ENV['BASE_URL'].$_SERVER['PHP_SELF']; ?>" method="post">
	<input type="hidden" name="WoLgID" id="langfield" value="<?php echo $_REQUEST['lang']; ?>" />
	<table class="tab3" cellspacing="0" cellpadding="5">
	<tr>
	<td class="td1 right">Language:</td>
	<td class="td1"><?php echo tohtml(getLanguage($_REQUEST['lang'])); ?></td>
	</tr>
	<tr>
	<td class="td1 right">Term:</td>
	<td class="td1"><input <?php echo $scrdir; ?> class="notempty setfocus checkoutsidebmp" data_info="Term" type="text" name="WoText" id="wordfield" value="" maxlength="250" size="40" /> <img src="<?php echo $_ENV['BASE_URL']; ?>/icn/status-busy.png" title="Field must not be empty" alt="Field must not be empty" /></td>
	</tr>
	<?php print_similar_terms_tabrow(); ?>	
	<tr>
	<td class="td1 right">Translation:</td>
	<td class="td1"><textarea class="textarea-noreturn checklength checkoutsidebmp" data_maxlength="500" data_info="Translation" name="WoTranslation" cols="40" rows="3"></textarea></td>
	</tr>
	<tr>
	<td class="td1 right">Tags:</td>
	<td class="td1">
	<?php echo getWordTags(0); ?>
	</td>
	</tr>
	<tr>
	<td class="td1 right">Romaniz.:</td>
	<td class="td1"><input type="text" class="checkoutsidebmp" data_info="Romanization" name="WoRomanization" value="" maxlength="100" size="40" /></td>
	</tr>
	<tr>
	<td class="td1 right">Sentence<br />Term in {...}:</td>
	<td class="td1"><textarea <?php echo $scrdir; ?> name="WoSentence" cols="40" rows="3" class="textarea-noreturn checklength checkoutsidebmp" data_maxlength="1000" data_info="Sentence"></textarea></td>
	</tr>
	<tr>
	<td class="td1 right">Status:</td>
	<td class="td1">
	<?php echo get_wordstatus_radiooptions(1); ?>
	</td>
	</tr>
	<tr>
	<td class="td1 right" colspan="2">  &nbsp;
		<?php echo createDictLinksInEditWin2($_REQUEST['lang'],'document.forms[\'newword\'].WoSentence','document.forms[\'newword\'].WoText'); ?>
		&nbsp; &nbsp;
	<input type="button" value="Cancel" onclick="{resetDirty(); location.href='edit_words.php';}" /> 
	<input type="submit" name="op" value="Save" /></td>
	</tr>
	</table>
	</form>
	
	<?php
	
}

// CHG

elseif (isset($_REQUEST['chg'])) {
	
	$sql = 'select * from ' . $tbpref . 'words, ' . $tbpref . 'languages where LgID = WoLgID and WoID = ' . $_REQUEST['chg'];
	$res = do_mysqli_query($sql);
	if ($record = mysqli_fetch_assoc($res)) {
		
		$wordlc = $record['WoTextLC'];
		$transl = repl_tab_nl($record['WoTranslation']);
		if($transl == '*') $transl='';
		$scrdir = ($record['LgRightToLeft'] ? ' dir="rtl" ' : '');
	
		?>
	
		<h4>Edit Term</h4>
		<script type="text/javascript" src="<?php echo $_ENV['BASE_URL']; ?>/js/unloadformcheck.js" charset="utf-8"></script>	
		<form name="editword" class="validate" action="<?php echo $_ENV['BASE_URL'].$_SERVER['PHP_SELF']; ?>#rec<?php echo $_REQUEST['chg']; ?>" method="post">
		<input type="hidden" name="WoID" value="<?php echo $record['WoID']; ?>" />
		<input type="hidden" name="WoLgID" id="langfield" value="<?php echo $record['WoLgID']; ?>" />
		<input type="hidden" name="WoOldStatus" value="<?php echo $record['WoStatus']; ?>" />
		<table class="tab3" cellspacing="0" cellpadding="5">
		<tr>
		<td class="td1 right">Language:</td>
		<td class="td1"><?php echo tohtml($record['LgName']); ?></td>
		</tr>
		<tr title="Normally only change uppercase/lowercase here!">
		<td class="td1 right">Term:</td>
		<td class="td1"><input <?php echo $scrdir; ?> class="notempty setfocus checkoutsidebmp" data_info="Term" type="text" name="WoText" id="wordfield" value="<?php echo tohtml($record['WoText']); ?>" maxlength="250" size="40" /> <img src="<?php echo $_ENV['BASE_URL']; ?>/icn/status-busy.png" title="Field must not be empty" alt="Field must not be empty" />
		</td></tr>
		<?php print_similar_terms_tabrow(); ?>
		<tr>
		<td class="td1 right">Translation:</td>
		<td class="td1"><textarea class="textarea-noreturn checklength checkoutsidebmp" data_maxlength="500" data_info="Translation" name="WoTranslation" cols="40" rows="3"><?php echo tohtml($transl); ?></textarea></td>
		</tr>
		<tr>
		<td class="td1 right">Tags:</td>
		<td class="td1">
		<?php echo getWordTags($record['WoID']); ?>
		</td>
		</tr>
		<tr>
		<td class="td1 right">Romaniz.:</td>
		<td class="td1"><input type="text" class="checkoutsidebmp" data_info="Romanization" name="WoRomanization" maxlength="100" size="40" 
		value="<?php echo tohtml($record['WoRomanization']); ?>" /></td>
		</tr>
		<tr>
		<td class="td1 right">Sentence<br />Term in {...}:</td>
		<td class="td1"><textarea <?php echo $scrdir; ?> class="textarea-noreturn checklength checkoutsidebmp" data_maxlength="1000" data_info="Sentence" name="WoSentence" cols="40" rows="3"><?php echo tohtml(repl_tab_nl($record['WoSentence'])); ?></textarea></td>
		</tr>
		<tr>
		<td class="td1 right">Status:</td>
		<td class="td1">
		<?php echo get_wordstatus_radiooptions($record['WoStatus']); ?>
		</td>
		</tr>
		<tr>
		<td class="td1 right" colspan="2">  &nbsp;
		<?php echo createDictLinksInEditWin2($record['WoLgID'],'document.forms[\'editword\'].WoSentence','document.forms[\'editword\'].WoText'); ?>
		&nbsp; &nbsp;
		<input type="button" value="Cancel" onclick="{resetDirty(); location.href='edit_words.php#rec<?php echo $_REQUEST['chg']; ?>';}" /> 
		<input type="submit" name="op" value="Change" /></td>
		</tr>
		</table>
		</form>
		<div id="exsent"><span class="click" onclick="do_ajax_show_sentences(<?php echo $record['LgID']; ?>, <?php echo prepare_textdata_js($wordlc) . ', ' . prepare_textdata_js("document.forms['editword'].WoSentence"); ?>);"><img src="<?php echo $_ENV['BASE_URL']; ?>/icn/sticky-notes-stack.png" title="Show Sentences" alt="Show Sentences" /> Show Sentences</span></div>	
<?php
	}
	mysqli_free_result($res);
}

// DISPLAY

else {
	
	if (substr($message,0,24) == "Error: Duplicate entry '" && 
		substr($message,-24) == "' for key 'WoLgIDTextLC'") {
		$lgID = $_REQUEST["WoLgID"] . "-";
		$message = substr($message,24+strlen($lgID));	
		$message = substr($message,0,strlen($message)-24);
		$message = "Error: Term '" . $message . "' already exists. Please go back and correct this!";
	} 	
	echo error_message_with_hide($message,0);

	if ($currenttext == '') {
		$sql = 'select count(*) as value from (select WoID from (' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) where (1=1) ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag .') as dummy';
	} else {
		$sql = 'select count(*) as value from (select WoID from (' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID), ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag .') as dummy';
	}
	$recno = get_first_value($sql);
	if ($debug) echo $sql . ' ===&gt; ' . $recno;
	
	$maxperpage = getSettingWithDefault('set-terms-per-page');

	$pages = $recno == 0 ? 0 : (intval(($recno-1) / $maxperpage) + 1);
	
	if ($currentpage < 1) $currentpage = 1;
	if ($currentpage > $pages) $currentpage = $pages;
	$limit = 'LIMIT ' . (($currentpage-1) * $maxperpage) . ',' . $maxperpage;

	$sorts = array('WoTextLC','lower(WoTranslation)','WoID desc','WoStatus, WoTextLC','WoTodayScore','textswordcount desc, WoTextLC asc','WoID');
	$lsorts = count($sorts);
	if ($currentsort < 1) $currentsort = 1;
	if ($currentsort > $lsorts) $currentsort = $lsorts;
	

	if ($currentlang != '') {
?>
<p><a href="<?php echo $_ENV['BASE_URL'].$_SERVER['PHP_SELF']; ?>?new=1&amp;lang=<?php echo $currentlang; ?>"><img src="<?php echo $_ENV['BASE_URL']; ?>/icn/plus-button.png" title="New" alt="New" /> New <?php echo tohtml(getLanguage($currentlang)); ?> Term ...</a></p>
<?php
	} else {
?>
<p><img src="<?php echo $_ENV['BASE_URL']; ?>/icn/plus-button.png" title="New" alt="New" /> New Term? - Set Language Filter first ...</p>
<?php
	}
?>

<form name="form1" action="#" onsubmit="document.form1.querybutton.click(); return false;">
<table class="tab1" cellspacing="0" cellpadding="5">
<tr>
<th class="th1" colspan="4">Filter <img src="<?php echo $_ENV['BASE_URL']; ?>/icn/funnel.png" title="Filter" alt="Filter" />&nbsp;
<input type="button" value="Reset All" onclick="resetAll('edit_words.php');" /></th>
</tr>
<tr>
<td class="td1 center" colspan="2">
Language:
<select name="filterlang" onchange="{setLang(document.form1.filterlang,'edit_words.php');}"><?php	echo get_languages_selectoptions($currentlang,'[Filter off]'); ?></select>
</td>
<td class="td1 center" colspan="2">
Text:
<select name="text" onchange="{val=document.form1.text.options[document.form1.text.selectedIndex].value; location.href='edit_words.php?page=1&amp;text=' + val;}"><?php echo get_texts_selectoptions($currentlang,$currenttext); ?></select>
</td>
</tr>
<tr>
<td class="td1 center" colspan="2" nowrap="nowrap">
Status:
<select name="status" onchange="{val=document.form1.status.options[document.form1.status.selectedIndex].value; location.href='edit_words.php?page=1&amp;status=' + val;}"><?php echo get_wordstatus_selectoptions($currentstatus,true,false); ?></select>
</td>
<td class="td1 center" colspan="2" nowrap="nowrap">
Term, Rom., Transl. (Wildc.=*):
<input type="text" name="query" value="<?php echo tohtml($currentquery); ?>" maxlength="50" size="15" />&nbsp;
<input type="button" name="querybutton" value="Filter" onclick="{val=document.form1.query.value; location.href='edit_words.php?page=1&amp;query=' + val;}" />&nbsp;
<input type="button" value="Clear" onclick="{location.href='edit_words.php?page=1&amp;query=';}" />
</td>
</tr>
<tr>
<td class="td1 center" colspan="2" nowrap="nowrap">
Tag #1:
<select name="tag1" onchange="{val=document.form1.tag1.options[document.form1.tag1.selectedIndex].value; location.href='edit_words.php?page=1&amp;tag1=' + val;}"><?php echo get_tag_selectoptions($currenttag1,$currentlang); ?></select>
</td>
<td class="td1 center" nowrap="nowrap">
Tag #1 .. <select name="tag12" onchange="{val=document.form1.tag12.options[document.form1.tag12.selectedIndex].value; location.href='edit_words.php?page=1&amp;tag12=' + val;}"><?php echo get_andor_selectoptions($currenttag12); ?></select> .. Tag #2
</td>
<td class="td1 center" nowrap="nowrap">
Tag #2:
<select name="tag2" onchange="{val=document.form1.tag2.options[document.form1.tag2.selectedIndex].value; location.href='edit_words.php?page=1&amp;tag2=' + val;}"><?php echo get_tag_selectoptions($currenttag2,$currentlang); ?></select>
</td>
</tr>
<?php if($recno > 0) { ?>
<tr>
<th class="th1" nowrap="nowrap">
<?php echo $recno; ?> Term<?php echo ($recno==1?'':'s'); ?>
</th><th class="th1" colspan="2" nowrap="nowrap">
<?php makePager ($currentpage, $pages, 'edit_words.php', 'form1', 1); ?>
</th><th class="th1" nowrap="nowrap">
Sort Order:
<select name="sort" onchange="{val=document.form1.sort.options[document.form1.sort.selectedIndex].value; location.href='edit_words.php?page=1&amp;sort=' + val;}"><?php echo get_wordssort_selectoptions($currentsort); ?></select>
</th></tr>
<?php } ?>
</table>
</form>

<?php
if ($recno==0) {
?>
<p>No terms found.</p>
<?php
} else {
?>
<form name="form2" action="<?php echo $_ENV['BASE_URL'].$_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="data" value="" />
<table class="tab1" cellspacing="0" cellpadding="5">
<tr><th class="th1 center" colspan="2">
Multi Actions <img src="<?php echo $_ENV['BASE_URL']; ?>/icn/lightning.png" title="Multi Actions" alt="Multi Actions" />
</th></tr>
<tr><td class="td1 center" colspan="2">
<b>ALL</b> <?php echo ($recno == 1 ? '1 Term' : $recno . ' Terms'); ?>:&nbsp; 
<select name="allaction" onchange="allActionGo(document.form2, document.form2.allaction,<?php echo $recno; ?>);"><?php echo get_allwordsactions_selectoptions(); ?></select>
</td></tr>
<tr><td class="td1 center">
<input type="button" value="Mark All" onclick="selectToggle(true,'form2');" />
<input type="button" value="Mark None" onclick="selectToggle(false,'form2');" />
</td>
<td class="td1 center">Marked Terms:&nbsp; 
<select name="markaction" id="markaction" disabled="disabled" onchange="multiActionGo(document.form2, document.form2.markaction);"><?php echo get_multiplewordsactions_selectoptions(); ?></select>
</td></tr></table>

<table class="sortable tab1"  cellspacing="0" cellpadding="5">
<tr>
<th class="th1 sorttable_nosort">Mark</th>
<th class="th1 sorttable_nosort">Act.</th>
<?php if ($currentlang == '') echo '<th class="th1 clickable">Lang.</th>'; ?>
<th class="th1 clickable">Term /<br />Romanization</th>
<th class="th1 clickable">Translation [Tags]<br /><span id="waitinfo">Please <img src="<?php echo $_ENV['BASE_URL']; ?>/icn/waiting2.gif" /> wait ...</span></th>
<th class="th1 sorttable_nosort">Se.<br />?</th>
<th class="th1 sorttable_numeric clickable">Stat./<br />Days</th>
<th class="th1 sorttable_numeric clickable">Score<br />%</th>

<?php
	if ($currentsort == 6) {
?>
<th class="th1 sorttable_numeric clickable" title="Word Count in Active Texts">WCnt<br />Txts</th>
<?php
	}
?>
</tr>

<?php

if ($currentsort == 6) {
	if ($currenttext != '')
		$sql = '';
	else
		$sql = 'select WoID, 0 AS textswordcount, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(WoSentence,\'\') like concat(\'%{\',WoText,\'}%\') as SentOK, WoStatus, LgName, LgRightToLeft, DATEDIFF( NOW( ) , WoStatusChanged ) AS Days, WoTodayScore AS Score, WoTomorrowScore AS Score2, ifnull(concat(\'[\',group_concat(distinct TgText order by TgText separator \', \'),\']\'),\'\') as taglist, WoTextLC, WoTodayScore from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID and WoTextLC NOT IN (SELECT DISTINCT TiTextLC from ' . $tbpref . 'textitems where TiLgID = LgID) ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag . ' UNION ';
	$sql .= 'select WoID, count(WoID) AS textswordcount, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(WoSentence,\'\') like concat(\'%{\',WoText,\'}%\') as SentOK, WoStatus, LgName, LgRightToLeft, DATEDIFF( NOW( ) , WoStatusChanged ) AS Days, WoTodayScore AS Score, WoTomorrowScore AS Score2, ifnull(concat(\'[\',group_concat(distinct TgText order by TgText separator \', \'),\']\'),\'\') as taglist, WoTextLC, WoTodayScore from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages, ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and WoLgID = LgID ';
	if ($currenttext != '') $sql .= 'and TiTxID = ' . $currenttext . ' ';
	$sql .= $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag . ' order by ' . $sorts[$currentsort-1] . ' ' . $limit;
} else {
	if ($currenttext == '') {
		$sql = 'select WoID, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(WoSentence,\'\') like concat(\'%{\',WoText,\'}%\') as SentOK, WoStatus, LgName, LgRightToLeft, DATEDIFF( NOW( ) , WoStatusChanged ) AS Days, WoTodayScore AS Score, WoTomorrowScore AS Score2, ifnull(concat(\'[\',group_concat(distinct TgText order by TgText separator \', \'),\']\'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages where WoLgID = LgID ' . $wh_lang . $wh_stat .  $wh_query . ' group by WoID ' . $wh_tag . ' order by ' . $sorts[$currentsort-1] . ' ' . $limit;
	} else {
		$sql = 'select distinct WoID, WoText, WoTranslation, WoRomanization, WoSentence, ifnull(WoSentence,\'\') like \'%{%}%\' as SentOK, WoStatus, LgName, LgRightToLeft, DATEDIFF( NOW( ) , WoStatusChanged ) AS Days, WoTodayScore AS Score, WoTomorrowScore AS Score2, ifnull(concat(\'[\',group_concat(distinct TgText order by TgText separator \', \'),\']\'),\'\') as taglist from ((' . $tbpref . 'words left JOIN ' . $tbpref . 'wordtags ON WoID = WtWoID) left join ' . $tbpref . 'tags on TgID = WtTgID), ' . $tbpref . 'languages, ' . $tbpref . 'textitems where TiLgID = WoLgID and TiTextLC = WoTextLC and TiTxID = ' . $currenttext . ' and WoLgID = LgID ' . $wh_lang . $wh_stat . $wh_query . ' group by WoID ' . $wh_tag . ' order by ' . $sorts[$currentsort-1] . ' ' . $limit;
	}
}

if ($debug) echo $sql;
flush();
$res = do_mysqli_query($sql);
while ($record = mysqli_fetch_assoc($res)) {
	$days = $record['Days'];
	if ( $record['WoStatus'] > 5 ) $days="-";
	$score = $record['Score'];
	if ( $score < 0 ) $score='<span class="scorered">0 <img src="'.$_ENV['BASE_URL'].'/icn/status-busy.png" title="Test today!" alt="Test today!" /></span>';
	else $score='<span class="scoregreen">' . floor($score) . ($record['Score2'] < 0 ? ' <img src="'.$_ENV['BASE_URL'].'/icn/status-away.png" title="Test tomorrow!" alt="Test tomorrow!" />' : ' <img src="'.$_ENV['BASE_URL'].'/icn/status.png" title="-" alt="-" />') . '</span>';
	echo '<tr>';
	echo '<td class="td1 center"><a name="rec' . $record['WoID'] . '"><input name="marked[]" type="checkbox" class="markcheck" value="' . $record['WoID'] . '" ' . checkTest($record['WoID'], 'marked') . ' /></a></td>';
	echo '<td class="td1 center" nowrap="nowrap">&nbsp;<a href="' . $_ENV['BASE_URL'].$_SERVER['PHP_SELF'] . '?chg=' . $record['WoID'] . '"><img src="'.$_ENV['BASE_URL'].'/icn/sticky-note--pencil.png" title="Edit" alt="Edit" /></a>&nbsp; <a class="confirmdelete" href="' . $_ENV['BASE_URL'].$_SERVER['PHP_SELF'] . '?del=' . $record['WoID'] . '"><img src="'.$_ENV['BASE_URL'].'/icn/minus-button.png" title="Delete" alt="Delete" /></a>&nbsp;</td>';
	if ($currentlang == '') echo '<td class="td1 center">' . tohtml($record['LgName']) . '</td>';
	echo '<td class="td1 "><span' . ($record['LgRightToLeft'] ? ' dir="rtl" ' : '') . '>' . tohtml($record['WoText']) . '</span>' . ($record['WoRomanization'] != '' ? (' / <span id="roman' . $record['WoID'] . '" class="edit_area clickedit">' . tohtml(repl_tab_nl($record['WoRomanization'])) . '</span>') : (' / <span id="roman' . $record['WoID'] . '" class="edit_area clickedit">*</span>')) . '</td>';
	echo '<td class="td1"><span id="trans' . $record['WoID'] . '" class="edit_area clickedit">' . tohtml(repl_tab_nl($record['WoTranslation'])) . '</span> <span class="smallgray2">' . tohtml($record['taglist']) . '</span></td>';
	echo '<td class="td1 center"><b>' . ($record['SentOK']!=0 ? '<img src="'.$_ENV['BASE_URL'].'/icn/status.png" title="' . tohtml($record['WoSentence']) . '" alt="Yes" />' : '<img src="'.$_ENV['BASE_URL'].'/icn/status-busy.png" title="(No valid sentence)" alt="No" />') . '</b></td>';
	echo '<td class="td1 center" title="' . tohtml(get_status_name($record['WoStatus'])) . '">' . tohtml(get_status_abbr($record['WoStatus'])) . ($record['WoStatus'] < 98 ? '/' . $days : '') . '</td>';
	echo '<td class="td1 center" nowrap="nowrap">' . $score . '</td>';
	if ($currentsort == 6) {
		echo '<td class="td1 center" nowrap="nowrap">' . $record['textswordcount'] . '</td>';
	}
	echo "</tr>\n";
}
mysqli_free_result($res);

?>
</table>
</form>

<script type="text/javascript">
//<![CDATA[
$('#waitinfo').addClass('hide');
//]]>
</script>

<?php if( $pages > 1) { ?>
<form name="form3" action="#">
<table class="tab1" cellspacing="0" cellpadding="5">
<tr>
<th class="th1" nowrap="nowrap">
<?php echo $recno; ?> Term<?php echo ($recno==1?'':'s'); ?>
</th><th class="th1" nowrap="nowrap">
<?php makePager ($currentpage, $pages, 'edit_words.php', 'form3', 2); ?>
</th></tr></table>
</form>
<?php } ?>

<?php
}

}

pageend();

?>