<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Exécute un script SQL MySQL/MariaDB écrit pour le client mysql : les blocs « DELIMITER $$ … DELIMITER ; »
 * (déclencheurs, procédures) sont respectés et chaque instruction est envoyée seule, pour qu'aucune erreur ne passe inaperçue.
 */
final class ScriptSql
{
    /** @return list<string> instructions du script, sans les directives DELIMITER */
    public static function instructions(string $sql): array
    {
        $instructions = [];
        $delimiteur = ';';
        $tampon = '';
        foreach (preg_split('/\r\n|\n/', $sql) as $ligne) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $ligne, $m)) {
                $delimiteur = $m[1];

                continue;
            }
            if ($tampon === '' && (trim($ligne) === '' || str_starts_with(ltrim($ligne), '--'))) {
                continue;
            }
            $tampon .= $ligne."\n";
            if (str_ends_with(rtrim($ligne), $delimiteur)) {
                $instruction = trim(substr(rtrim($tampon), 0, -strlen($delimiteur)));
                if ($instruction !== '') {
                    $instructions[] = $instruction;
                }
                $tampon = '';
            }
        }
        if (trim($tampon) !== '') {
            $instructions[] = trim($tampon);
        }

        return $instructions;
    }

    public static function executer(string $sql): int
    {
        $instructions = self::instructions($sql);
        foreach ($instructions as $instruction) {
            DB::unprepared($instruction);
        }

        return count($instructions);
    }
}
