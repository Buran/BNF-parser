<?php
include 'lib/utf8.php';
include 'Parser/unescape.php';
include 'Parser/bnf/Collection.php';
include 'Parser/bnf/Rule.php';
include 'Parser/BNFRulesParser.php';
include 'Parser/BNFParser.php';
include 'Parser/bnf/RuleRepeat.php';
include 'Parser/bnf/Regexp.php';
$RulesParser = new BNFRulesParser(new RuleRepeat(), new BNFRegexp());

$tests = array(
	array(
		array(dirname(__FILE__) . '/tests/BNF-analyser/comment.bnf'),
		dirname(__FILE__) . '/tests/BNF-analyser/comment.out.bnf'
	),
	array(
		array(dirname(__FILE__) . '/tests/BNF-analyser/multiline.bnf'),
		dirname(__FILE__) . '/tests/BNF-analyser/multiline.out.bnf'
	)
);

foreach ($tests as $test) {
	$RulesParser->reset();
	foreach ($test[0] as $file) {
		$RulesParser->addBNFFile($file);
	}
	echo strcmp(trim(file_get_contents($test[1])), trim((string)$RulesParser));
}

$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/test.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/primitives/base.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/primitives/color.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/keywords.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/filter.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/vendor-prefixes.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/box/box-shadow.bnf');

//$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/primitives/w3c.bnf');
//$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/selector.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/background/background.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/images/gradient/gradient.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/images/gradient/webkit.bnf');
$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/images/gradient/mozilla.bnf');
//$RulesParser->addBNFFile(dirname(__FILE__) . '/bnf-rules/css/margin.bnf');


$cur_char = 0;
//$str = "rl(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAAAaCAYAAABByvnlAAACXUlEQVR42u3aPW9SURjAcUpFY2wp2A4mddA09gXbOpi4GKOpjVih8lJuy4VyeSkBirxcLgWKVEqNsQ7awTi5+gUUbZz9AA4ubsaoSQfSQU00jdE+PpeAIaYyVOAwPCS/ieQM559zn3uSq1DU/ynRSRT4TxGUQDEUasB6TxqwRruqG+Ps0d4+KSpl3q/ff/hr49FjIM1VL8hoT49WzK3d+1G4+wDSK3dAzKxCPJ0nTfSvGP0HVKqF5HL+OwaBhBwidYu0wF4xDiKT4Ft8W40RW1ohLbJXkHO60TOFbGEdpOwaRJI50kJ/x9AqlZ1cVMrupHK34UYiS1qsNkYHmjSauaIcIyLdhMV4hrRYbZATXd1qt5jO78rTPhhLEwaqMVRoyuUNvZOWVyEYTUEgskQYqAYZH9aNiSK+dsnHxh+WCCPVG/mY4A+X5Bi+kEgYKt87Bk4NjTg8wS/eYBw8gRhhSAEAHXqDpc8+7//s8oVB8EcIQ+Ug6JB11rVls3tAjkLYKU90DHIkJqXPGy1zwDm94PSGCCPVIJ1IbZrhX09b7eBwB8DpCRIG/twKMUjX0+KLfoPJtmudm8c/A4SB2iDyLFFzvLBhNHPAuxZog1gGqUQ5XCptazHIN7ONpw1iHaQSRS0mMxPXrs8Ax7tpk9ogiDzgNXhCXuE8ATs9utgGqUTp/vDx07GpactXo2UWeMFPm8UySCWK5vnmy8HLVww7eoMZbHYBHBSGaRD5rav3WXFzHE/Km4sT+p8XLk0Caa66X8lVomjQcTSABtHQPoyg00iHhve5Rq2rDVijLf0GrrFY66SKDHwAAAAASUVORK5CYII=);}
//h2 {background: #4571bb; background: -moz-linear-gradient(top, #4571bb, #2a4572); background: -webkit-gradient(linear, left top, left bottom, from(#4571bb), to(#2a4572))";
//$str = file_get_contents('Test/fixture/css/background.css');
//$str = file_get_contents('Test/test');
//$str = file_get_contents('Test/box-shadow');
$str = file_get_contents('tests/source-analyser/images/gradient/gradient');
$found = array();
$n = 0;
while ($cur_char < strlen($str)) {
	$Parser = new BNFParser($RulesParser->parsedData, substr($str, $cur_char));
	$Parser->setCurrentTag('gradient');
	$res = $Parser->testBNF();
	if ($res) {
		array_push($found, array($cur_char, $Parser->current_character));
		$cur_char += $Parser->current_character ? $Parser->current_character : 1;
	} else {
		$cur_char++;
	}
}

//echo $GLOBALS['times'];
$cur_char = 0;
echo '<pre>';
foreach ($found as $v) {
	echo substr($str, $cur_char, $v[0] - $cur_char);
	echo '<span style="background: yellow;">' . substr($str, $v[0], $v[1]) . '</span>';
	$cur_char = $v[0] + $v[1];
}
echo substr($str, $cur_char);
echo '</pre>';
