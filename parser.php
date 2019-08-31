<?php
define('DEBUG', 1);
define('TEST', FALSE);

#var_dump(file_exists('NMS_REALITY_GCPRODUCTTABLE.EXML'));
#$fh = fopen('NMS_REALITY_GCPRODUCTTABLE.EXML', 'r');
#var_dump(fgets($fh));
#fclose($fh);

function printAttrs($node) {
    echo $node->nodeName;
    if(!$node->hasAttributes()) {
        echo "\n";
        return;
    }
    echo ' (';
    foreach($node->attributes as $attribute) {
        echo $attribute->nodeName.' = '.$attribute->nodeValue.', ';
    }
    echo ")\n";
}

$translationFiles = [
    'NMS_LOC1_ENGLISH.EXML',
    'NMS_LOC4_ENGLISH.EXML',
    'NMS_LOC5_ENGLISH.EXML',
    'NMS_UPDATE3_ENGLISH.EXML'
];

$ingredientsFiles = [
    'NMS_REALITY_GCPRODUCTTABLE.EXML' => [
        'type' => 'GcProductData.xml',
        'id' => 'Id',
        'name' => 'NameLower'
    ],
    'NMS_REALITY_GCSUBSTANCETABLE.EXML' => [
        'type' => 'GcRealitySubstanceData.xml',
        'id' => 'ID',
        'name' => 'NameLower'
    ]
];

if(TEST) {
    $stringsRaw = [
        'FOOD_R_CREAM' => [],
        'FOOD_NOPE_L' => [],
        'FOOD_BLOB_VEG_NAME_L' => [],
        'NAMEGEN_WEAP_PROPERTY_25' => []
    ];
} else {
    $stringsRaw = [];
}

if(TEST) {
    $itemsTransient = [
        'FOOD_R_CREAM' => []
    ];
} else {
    $itemsTransient = [];
}

$ingredients = [];
$unlockables = [];

$languageMishaps = [
    'Curiousity' => 'Curiosity'
];

##### List crafts requiring a blueprint #####

$reader = new XMLReader();
$reader->open('UNLOCKABLEITEMTREES.EXML');
while($reader->read()) {
    if($reader->nodeType !== XMLREADER::ELEMENT
      || $reader->localName !== 'Property'
      || $reader->getAttribute('name') !== 'CraftProducts'
      || $reader->getAttribute('value') !== 'GcUnlockableItemTrees.xml') continue;

    $node = $reader->expand();
    $dom = new DomDocument();
    $dom->formatOutput = true;
    $n = $dom->importNode($node, true);
    $dom->appendChild($n);
    $xpath = new DomXpath($dom);
    $temp = $xpath->query('.//Property[@name="Unlockable"]');
    if($temp === FALSE) die("Error: Tree does not have an Unlockable property\n");
    foreach($temp as $unlockable) {
        $unlockable = $unlockable->attributes->getNamedItem('value')->value;
        $unlockables[] = $unlockable;
    }

    if(DEBUG >= 2) echo "Unlockable: $unlockable\n";
}

##### List products & their crafting recipe(s) #####

$reader->open('NMS_REALITY_GCPRODUCTTABLE.EXML');
while($reader->read()) {
    if($reader->nodeType !== XMLREADER::ELEMENT
      || $reader->localName !== 'Property'
      || $reader->getAttribute('value') !== 'GcProductData.xml') continue;

    $node = $reader->expand();
    $dom = new DomDocument();
    $dom->formatOutput = true;
    $n = $dom->importNode($node, true);
    $dom->appendChild($n);
    $xpath = new DomXpath($dom);
    $temp = $xpath->query('./Property[@name="Id"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Id property\n");
    $id = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="NameLower"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a NameLower property\n");
    $name = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Subtitle"]/Property[@name="Value"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Subtitle property\n");
    $subtitle = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Description"]/Property[@name="Value"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Description property\n");
    $description = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Icon"]/Property[@name="Filename"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have an Icon property\n");
    $icon = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="Type"]/Property[@name="ProductCategory"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Type property\n");
    $type = $temp->item(0)->attributes->getNamedItem('value')->value;
    if(isset($languageMishaps[$type])) $type = $languageMishaps[$type];

#    if(strstr($icon, 'TEXTURES/UI/FRONTEND/ICONS/COOKINGPRODUCTS') === FALSE) continue;
    if($type !== 'Consumable' || $subtitle !== 'FOOD_COOKED_SUB') continue;
#    if($type !== 'Consumable' || $subtitle !== 'BAIT_MEAT_SUB') continue;
#    if($type !== 'Consumable' || $subtitle !== 'POWERPROD_SUB') continue;

    $itemsTransient[$id]['name'] = $name;
    $stringsRaw[$name] = [];

    $itemsTransient[$id]['subtitle'] = $subtitle;
    $stringsRaw[$subtitle] = [];

    $itemsTransient[$id]['description'] = $description;
    $stringsRaw[$description] = [];

    $itemsTransient[$id]['icon'] = $icon;
    $itemsTransient[$id]['type'] = $type;

    if(DEBUG >= 2) echo "Item (transient): $id ($name) -> $subtitle // $icon\n";

    $ingredientsTemp = $xpath->query('./Property[@name="Requirements"]/Property[@value="GcTechnologyRequirement.xml"]');
    if($ingredientsTemp === FALSE) die("Error: Product does not have a Requirements property\n");
    if($ingredientsTemp->length <= 0) continue;

    $temp = $xpath->query('./Property[@name="DefaultCraftAmount"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have a DefaultCraftAmount property\n");
    $defaultCraftAmount = $temp->item(0)->attributes->getNamedItem('value')->value;

    $temp = $xpath->query('./Property[@name="CraftAmountMultiplier"]');
    if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have a CraftAmountMultiplier property\n");
    $craftAmountMultiplier = $temp->item(0)->attributes->getNamedItem('value')->value;

    $recipe = [
        'quantity' => $defaultCraftAmount * $craftAmountMultiplier,
        'unlock' => in_array($id, $unlockables, TRUE) ? TRUE : FALSE
    ];

    foreach($ingredientsTemp as $ingredient) {
        $ingredientId = $xpath->query('./Property[@name="ID"]', $ingredient)->item(0)->attributes->getNamedItem('value')->value;
        $quantity = $xpath->query('./Property[@name="Amount"]', $ingredient)->item(0)->attributes->getNamedItem('value')->value;
        $recipe['ingredients'][] = [
            'name' => $ingredientId,
            'quantity' => $quantity
        ];

        $ingredients[$ingredientId] = [];
    }

    $itemsTransient[$id]['recipes']['Craft'][] = $recipe;
}

##### Searching for refining/cooking recipes #####

$reader->open('NMS_REALITY_GCRECIPETABLE.EXML');
while($reader->read()) {
    if($reader->nodeType !== XMLREADER::ELEMENT
      || $reader->localName !== 'Property'
      || $reader->getAttribute('value') !== 'GcRefinerRecipe.xml') continue;

    $node = $reader->expand();
    $dom = new DomDocument();
    $dom->formatOutput = true;
    $n = $dom->importNode($node, true);
    $dom->appendChild($n);
    $xpath = new DomXpath($dom);
    foreach($itemsTransient as $key => $value) {
        $results = $xpath->query('./Property[@name="Result" and @value="GcRefinerRecipeElement.xml"]/Property[@name="Id" and @value="'.$key.'"]');
        if($results === FALSE) die("Error: Recipe search for $key failed\n");
        if($results->length > 1) die("Error: Too many results searching recipe for $key\n");
        if($results->length <= 0) continue;

        $temp = $xpath->query('./Property[@name="Id"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have a Id property\n");
        $id = $temp->item(0)->attributes->getNamedItem('value')->value;

        $temp = $xpath->query('./Property[@name="Name"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have a Name property\n");
        $name = $temp->item(0)->attributes->getNamedItem('value')->value;

        $temp = $xpath->query('./Property[@name="TimeToMake"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have a TimeToMake property\n");
        $time = $temp->item(0)->attributes->getNamedItem('value')->value;

        $temp = $xpath->query('./Property[@name="Cooking"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have an Cooking property\n");
        $action = ($temp->item(0)->attributes->getNamedItem('value')->value === 'True') ? 'Cook' : 'Refine';

        $temp = $xpath->query('./Property[@name="Result" and @value="GcRefinerRecipeElement.xml"]/Property[@name="Amount"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Recipe for $key does not have an Amount property\n");
        $quantity = $temp->item(0)->attributes->getNamedItem('value')->value;

        $recipe = [
            'name' => $name,
            'quantity' => $quantity,
            'time' => $time
        ];
        $stringsRaw[$name] = [];

        $ingredientsTemp = $xpath->query('./Property[@name="Ingredients"]/Property[@value="GcRefinerRecipeElement.xml"]');
        foreach($ingredientsTemp as $ingredient) {
            $ingredientId = $xpath->query('./Property[@name="Id"]', $ingredient)->item(0)->attributes->getNamedItem('value')->value;
            $quantity = $xpath->query('./Property[@name="Amount"]', $ingredient)->item(0)->attributes->getNamedItem('value')->value;
            $recipe['ingredients'][] = [
                'name' => $ingredientId,
                'quantity' => $quantity
            ];

            $ingredients[$ingredientId] = [];
        }

        $itemsTransient[$key]['recipes'][$action][$id] = $recipe;
    }
}

foreach($itemsTransient as $id => $item) {
    if(isset($ingredients[$id])) unset($ingredients[$id]);
}

##### List ingredients #####

foreach($ingredientsFiles as $ingredientsFile => $structure) {
    $reader->open($ingredientsFile);
    while($reader->read()) {
        if($reader->nodeType !== XMLREADER::ELEMENT
          || $reader->localName !== 'Property'
          || $reader->getAttribute('value') !== $structure['type']) continue;

        $node = $reader->expand();
        $dom = new DomDocument();
        $dom->formatOutput = true;
        $n = $dom->importNode($node, true);
        $dom->appendChild($n);
        $xpath = new DomXpath($dom);
        $temp = $xpath->query('./Property[@name="'.$structure['id'].'"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a Id property\n");
        $id = $temp->item(0)->attributes->getNamedItem('value')->value;

        if(!isset($ingredients[$id])) continue;

        $temp = $xpath->query('./Property[@name="'.$structure['name'].'"]');
        if($temp === FALSE || $temp->length !== 1) die("Error: Product does not have a NameLower property\n");
        $name = $temp->item(0)->attributes->getNamedItem('value')->value;

        $ingredients[$id]['name'] = $name;
        if(isset($stringsRaw[$name])) echo "Already existing!\n";
        $stringsRaw[$name] = [];

        if(DEBUG >= 2) echo "Ingredient: $id ($name)\n";
    }
}

echo 'Loaded '.count($itemsTransient).' items, '.count($stringsRaw).' strings & '.count($ingredients)." ingredients\n";

##### Searching for translations #####

$strings = [];

foreach($translationFiles as $translationFile) {
    $reader->open($translationFile);
    while($reader->read()) {
        if($reader->nodeType !== XMLREADER::ELEMENT
          || $reader->localName !== 'Property'
          || $reader->getAttribute('value') !== 'TkLocalisationEntry.xml') continue;

        $node = $reader->expand();
        $dom = new DomDocument();
        $dom->formatOutput = true;
        $n = $dom->importNode($node, true);
        $dom->appendChild($n);
        $xpath = new DomXpath($dom);
        foreach($stringsRaw as $key => $value) {
            $results = $xpath->query('./Property[@name="Id" and @value="'.$key.'"]/../Property[@name="English"]/Property[@name="Value"]');
            if($results === FALSE) die("Error: Name search for $key in $translationFile failed\n");
            if($results->length > 1) die("Error: Too many results searching name for $key in $translationFile\n");
            if($results->length <= 0) continue;

            $translation = $results->item(0)->attributes->getNamedItem('value')->value;
            $translation = preg_replace([
              '`^Requested Operation: `',
              '`^Processor Setting: `'
             ],
            '', $translation);
            $strings[$key]['translation'] = $translation;
            $strings[$key]['file'] = $translationFile;
            unset($stringsRaw[$key]);
            if(DEBUG >= 2) echo "Translation: $key -> $translation\n";
        }

        if(!$stringsRaw) break 2;
    }
}

echo 'Found '.count($strings)." translations\n";

##### Correcting translations mishaps #####

foreach($strings as $key => &$data) {
    if(isset($languageMishaps[$data['translation']])) {
        $data['translation'] = $languageMishaps[$data['translation']];
        $data['manuallyModified'] = TRUE;
    }
}
unset($data);

##### Combining items & translated strings #####

$items = [];

foreach($itemsTransient as $key => $item) {
    if(!isset($strings[$item['name']]) || !isset($strings[$item['subtitle']])) {
        // Untranslated string(s)
        continue;
    }

/*#    if(strstr($icon, 'TEXTURES/UI/FRONTEND/ICONS/COOKINGPRODUCTS') === FALSE) continue;
#    if($type !== 'Consumable' || $subtitle !== 'FOOD_COOKED_SUB') continue;
    if($item['type'] !== 'Consumable' || $item['subtitle'] !== 'BAIT_MEAT_SUB') continue;*/

    $item['subtitle'] = $strings[$item['subtitle']]['translation'];
    $item['description'] = $strings[$item['description']]['translation'];
/*    $name = $item['name'];
    unset($item['name']);
    $items[$strings[$name]['translation']] = $item;*/
    $item['name'] = $strings[$item['name']]['translation'];

    if(isset($item['recipes'])) {
        foreach($item['recipes'] as $category => &$element) {
            foreach($element as &$recipe) {
                if(isset($recipe['name'])) $recipe['name'] = $strings[$recipe['name']]['translation'];
                foreach($recipe['ingredients'] as &$ingredient) {
                    if(isset($itemsTransient[$ingredient['name']])) {
                        $ingredient['name'] = $strings[$itemsTransient[$ingredient['name']]['name']]['translation'];
                    } else {
                        $ingredient['name'] = $strings[$ingredients[$ingredient['name']]['name']]['translation'];
                    }
                }
            }
            unset($recipe);
        }
        unset($element);
    }

    $items[$key] = $item;

    if(DEBUG >= 2) echo 'Item: '.$key.' ('.$item['name'].') -> '.$item['subtitle'].' // '.$item['icon']."\n";
}

##### Output #####

#uasort($items,
#  function($a, $b) {
#    return strcmp($a['name'], $b['name']);
#  }
#);
ksort($items);

#$temp = [];
#foreach($items as $item) {
#    $temp[$item['name']] = $item['icon'];
#}
#if(($json = json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
if(($json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
    file_put_contents('items.json', $json, LOCK_EX);
}

if(DEBUG) {
    if(($json = json_encode($unlockables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
        file_put_contents('debug_unlockables.json', $json, LOCK_EX);
    }
    ksort($itemsTransient);
    if(($json = json_encode($itemsTransient, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
        file_put_contents('debug_itemsTransient.json', $json, LOCK_EX);
    }
    if(($json = json_encode($ingredients, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
        file_put_contents('debug_ingredients.json', $json, LOCK_EX);
    }
    if(($json = json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE) {
        file_put_contents('debug_strings.json', $json, LOCK_EX);
    }
}

$list = '';
$count = 0;
foreach($ingredients as $id => $ingredient) {
    if(!$ingredient) {
        $list .= $list ? ', '.$id : $id;
        $count++;
    }
}
if($list) echo "No item could be found for the following ingredients ($count): $list\n";

if($stringsRaw) {
    $list = '';
    foreach($stringsRaw as $key => $value) {
        $list .= $list ? ', '.$key : $key;
    }
    echo 'No translation could be found for the following handles ('.count($stringsRaw)."): $list\n";
}
?>
