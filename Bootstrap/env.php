<?php
/**
 * Chargeur de variables d'environnement (.env), sans dépendance externe.
 * -------------------------------------------------------------
 * Lit un fichier `.env` à la racine du projet (format KEY=VALUE, lignes
 * `#` ignorées) et peuple `getenv()`/`$_ENV`/`$_SERVER`. N'écrase jamais
 * une variable déjà définie par le vrai environnement serveur (Apache,
 * PHP-FPM, panneau d'hébergement) : le fichier `.env` ne sert que de
 * valeur par défaut locale/dev.
 */

declare(strict_types=1);

if (!function_exists('load_env')) {
    function load_env(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Retire les guillemets simples/doubles englobants.
            if (strlen($value) >= 2 && (
                ($value[0] === '"' && $value[-1] === '"') ||
                ($value[0] === "'" && $value[-1] === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            if ($key === '' || getenv($key) !== false) {
                continue; // ne jamais écraser une variable d'environnement réelle.
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env_value')) {
    /** Lit une variable d'environnement avec valeur par défaut de repli. */
    function env_value(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('env_bool')) {
    /**
     * Lit une variable d'environnement booléenne. Un simple `(bool)` sur une
     * valeur d'environnement (toujours une chaîne) est un piège classique :
     * `(bool) "false"` vaut `true` en PHP. On interprète donc explicitement
     * les chaînes "false"/"0"/"no"/"off"/"" comme fausses.
     */
    function env_bool(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return !in_array(strtolower(trim((string) $value)), ['false', '0', 'no', 'off', ''], true);
    }
}
