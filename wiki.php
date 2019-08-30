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
        case 'name':
            $replacements[$type] = $value;
            break;

        case 'icon':
            $replacements[$type] = basename($value, '.DDS').'.png';
            break;

        case 'recipes':
            $recipes = '';
            foreach($value as $category => $item) {
                switch($category) {
                    case 'Craft':
                        foreach($item as $recipe) {
                            $temp = '{{PoC-Craft|';
                            foreach($recipe['ingredients'] as $ingredient) {
                                    $temp .= $ingredient['name'].','.$ingredient['quantity'].';';
                            }
                            $temp .= $recipe['quantity'].($recipe['unlock'] ? '|blueprint=yes' : '').'}}';
                        }
                        $recipes .= $recipes ? "\n".$temp : $temp;
                        break;

                    case 'Refine':
                        $temp = '{{PoC-Refine';
                        foreach($item as $recipe) {
                            $temp .= '|';
                            foreach($recipe['ingredients'] as $ingredient) {
                                    $temp .= $ingredient['name'].','.$ingredient['quantity'].';';
                            }
                            $temp .= $recipe['quantity'].';'.$recipe['time'].'%'.$recipe['name'];
                        }
                        $temp .= '}}';
                        $recipes .= $recipes ? "\n".$temp : $temp;
                        break;


                    case 'Cook':
                        $temp = '{{PoC-Cook';
                        foreach($item as $recipe) {
                            $temp .= '|';
                            foreach($recipe['ingredients'] as $ingredient) {
                                    $temp .= $ingredient['name'].','.$ingredient['quantity'].';';
                            }
                            $temp .= $recipe['quantity'].';'.$recipe['time'].'%'.$recipe['name'];
                        }
                        $temp .= '}}';
                        $recipes .= $recipes ? "\n".$temp : $temp;
                        break;

                    default:
                        die('Unknown recipe action: '.$recipe['action']);
                }
            }
            $replacements[$type] = rtrim($recipes);
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
foreach($items as $id => $item) {
    $replacements = [];
    foreach($item as $key => $value) {
        if(!in_array($key, $types, TRUE)) continue;

        if(is_string($value) && isset($conf[$value])) $value = $conf[$value];
        generateVariants($replacements, $key, $value);
    }
    if(DEBUG) var_dump($replacements);
    if(!isset($replacements['name'])) die("No name key for item $id");

    $output = preg_replace_callback($pattern,
    function ($matches) use (&$replacements) {
        return $replacements[$matches['tag']];
    }
    , $template);
    file_put_contents('pages/'.$item['name'], $output);
    if(DEBUG) var_dump($output);
}
?>
