<?php
define('DEBUG', 0);

function findBaseType($match) {
    $match = str_replace('Link', '', $match);
    return strtolower($match);
}

function makeLowerCaseLink($string) {
    return "[[$string|".strtolower($string).']]';
}

function generateVariants(&$replacements, $type, $value) {
    switch($type) {
        case 'icon':
            $replacements[$type] = basename($value, '.DDS').'.png';
            break;

        default:
            $replacements[ucfirst($type)] = $value;
            $replacements[$type] = strtolower($value);
            $replacements[$type.'Link'] = makeLowerCaseLink($value);
    }
}

$conf = json_decode(file_get_contents('wikiConf.json'), TRUE);
$items = json_decode(file_get_contents('items.json'), TRUE);
$template = file_get_contents('template.wiki');
$pattern = '`µ£(?<tag>.+?)£µ`';

if(preg_match_all($pattern, $template, $matches) === FALSE) die("Error: Failed to match patterns in template file\n");
$types = [];
foreach($matches['tag'] as $match) {
    $types[] = findBaseType($match);
}
$types = array_unique($types);

if(!file_exists('pages')) mkdir('pages');
foreach($items as $name => $item) {
    $replacements = [
      'name' => $name
    ];
    foreach($item as $key => $value) {
        if(!in_array($key, $types, TRUE)) continue;

        if(isset($conf[$value])) $value = $conf[$value];
        generateVariants($replacements, $key, $value);
    }
    if(DEBUG) var_dump($replacements);

    $output = preg_replace_callback($pattern,
    function ($matches) use (&$replacements) {
        return $replacements[$matches['tag']];
    }
    , $template);
    file_put_contents("pages/$name", $output);
    if(DEBUG) var_dump($output);
}
?>
