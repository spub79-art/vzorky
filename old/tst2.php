<?php $array = array(
    array('name', 'age', 'gender' ),
    array('Ian', 24, 'male'),
    array('Janice', 21, 'female')
);
print_r($array);
$fields = implode(', ', array_shift($array));

$values = array();
foreach ($array as $rowValues) {
    foreach ($rowValues as $key => $rowValue) {
         $rowValues[$key] = ($rowValues[$key]);
    }

    $values[] = "(" . implode(', ', $rowValues) . ")";
}

$query = "INSERT INTO table_name ($fields) VALUES (" . implode (', ', $values) . ")";
echo $query . "<br/>";

?>