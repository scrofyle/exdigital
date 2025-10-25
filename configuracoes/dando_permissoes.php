<?php
// Script para aplicar permissões
$commands = [
    'chmod 755 exdigital/',
    'chmod 755 exdigital/uploads/',
    'chmod 755 exdigital/logs/',
    'chmod 755 exdigital/exports/',
    'chmod 644 exdigital/*.php'
];

foreach ($commands as $command) {
    system($command, $returnCode);
    echo "Executado: $command - Código: $returnCode<br>";
}
?>
