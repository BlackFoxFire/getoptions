<?php

/**
 * GetOptions.php
 * @Auteur: Christophe Dufour
 * 
 * GetOptions permet de gérer les options passées en ligne de commande un script PHP
 */

namespace Blackfox\GetOptions;

use Throwable;
use Exception;

class GetOptions {

    public const SHORT_OPTIONS_VALIDATION_PATTERN = "/^([a-zA-Z0-9]{1}[:]?)+$/";
    public const LONG_OPTIONS_VALIDATION_PATTERN = "/^[a-zA-Z0-9]{2,}[:]?$/";
    public const SHORT_OPTIONS_PATTERN = "/^-([a-zA-Z0-9]{1})[=]?([a-zA-Z0-9]*)$/";
    public const LONG_OPTIONS_PATTERN = "/^--([a-zA-Z0-9]{2,})[=]?([.]*)$/";


    protected static string $short_options;
    protected static array $long_options;
    protected static bool $showErrors;
    protected static bool $exitOnError;
    protected static array $options = [];
    protected static array $positional = [];
    protected static array $invalidOptions = [];

    /**
     * Gestionnaire d'exceptions
     * 
     * @param Throwable $ex
     * @return void
     */
    public static function exceptionHandler(Throwable $ex): void
    {
        global $argv;
        fwrite(STDERR, "Erreur: " . $ex->getMessage() . PHP_EOL);
        exit(1);
    }

    /**
     * Analyse les options passées à la ligne de commande et retourne la valeur de options
     * 
     * @param string $short_options
     * Chaine de caractère des options courtes
     * @param array $long_options
     * [optional] Tableau des options longues
     * @param bool $showErrors
     * [optional] Affiche un message d'erreur en cas d'option invalide
     * @param bool $exitOnError
     * [optional] Arrête le script si une option invalide est trouvée. Ignoré si $showErrors = false
     * @return array
     * 
     */
    public static function options(string $short_options, array $long_options = [], bool $showErrors = true, $exitOnError = false): array
    {
        global $argv;
        self::$short_options = $short_options;
        self::$long_options = $long_options;
        self::$showErrors = $showErrors;
        self::$exitOnError = $exitOnError;

        set_exception_handler("Blackfox\GetOptions\GetOptions::exceptionHandler");

        self::verifyShortOption();

        if(!empty($long_options)) {
            self::verifyLongOption();
        }

        $arguments = array_slice($argv, 1);

        while($subject = current($arguments)) {
            if(preg_match(self::SHORT_OPTIONS_PATTERN, $subject)) {
                self::analyzeShortOptions($subject, $arguments);
            }
            elseif(preg_match(self::LONG_OPTIONS_PATTERN, $subject, $matches)) {
                self::analyzeLongOptions($subject, $matches, $arguments);
            }
            else {
                self::$positional[] = $subject;
            }

            next($arguments);
        }

        return self::$options;
    }

    /**
     * Vérifie la chaine de caractères des options courtes
     * 
     * @return void
     * @throws Exception
     */
    protected static function verifyShortOption(): void
    {
        if(!preg_match(self::SHORT_OPTIONS_VALIDATION_PATTERN, self::$short_options)) {
            throw new Exception("Erreur: la valeur de l'argument short_options n'est pas valide.");
        }
    }

    /**
     * Vérifie le tableau des options longues
     * 
     * @return void
     * @throws Exception
     */
    protected static function verifyLongOption(): void
    {
        foreach(self::$long_options as $options) {
            if(!preg_match(self::LONG_OPTIONS_VALIDATION_PATTERN, $options)) {
                throw new Exception("Erreur: la valeur de l'argument long_options n'est pas valide.");
            }
        }
    }

    /**
     * Analise les options courtes
     * 
     * @param string $subject
     * @param array &$arguments
     * @return void
     */
    protected static function analyzeShortOptions(string $subject, array &$arguments): void
    {
        $subject = substr($subject, 1);

        while(!empty($subject)) {
            if(($pos = strpos(self::$short_options, $subject[0])) !== false) {
                if(isset(self::$short_options[$pos+1]) && self::$short_options[$pos+1] == ':') {
                    if(isset($subject[1])) {
                        if($subject[1] == '=') {
                            self::$options[$subject[0]] = substr($subject, 2);
                        }
                        else {
                            self::$options[$subject[0]] = substr($subject, 1);
                        }
                    }
                    else {
                        if(($next = next($arguments)) !== false) {
                            if($next[0] != '-') {
                                self::$options[$subject] = $next;
                            }
                            else {
                                throw new \Exception("Valeur requise pour l'option -- $subject.");
                            }
                        }
                        else {
                            throw new Exception("Valeur requise pour l'option -- $subject.");
                        }
                    }

                    $subject = "";
                }
                else {
                    self::$options[$subject[0]] = false;
                }
            }
            else {
                self::$invalidOptions [] = $subject[0];

                if(self::$showErrors) {
                    if(!self::$exitOnError) {
                        fwrite(STDERR, "Option invalide ignionée -- $subject[0]" . PHP_EOL);
                    }
                    else {
                        throw new Exception("Option invalide -- $subject[0]");
                    }
                }
            }

            $subject = substr($subject, 1);
        }
    }

    /**
     * Analise les options longues
     * 
     * @param string $subject
     * @param array $matches
     * @param array &$arguments
     * @return void
     */
    protected static function analyzeLongOptions(string $subject, array $matches, array &$arguments): void
    {
        $subject = $matches[1];

        if(in_array($subject, self::$long_options) || 
            in_array($subject . ":", self::$long_options)) {
            
            if(($pos = array_search($subject, self::$long_options)) == false) {
                $pos = array_search($subject . ':', self::$long_options);
            }

            if(substr(self::$long_options[$pos], -1, 1) == ':') {
                if(!empty($matches[2])) {
                    self::$options[$subject] = $matches[2];
                }
                else {
                    if(($next = next($arguments)) !== false) {
                        if($next[0] != '-') {
                            self::$options[$subject] = $next;
                        }
                        else {
                            throw new Exception("Valeur requise pour l'option -- $subject.");
                        }
                    }
                    else {
                        throw new Exception("Valeur requise pour l'option -- $subject.");
                    }
                }
            }
            else {
                self::$options[$subject] = false;
            }
        }
        else {
            self::$invalidOptions[] = $subject;

            if(self::$showErrors) {
                if(!self::$exitOnError) {
                        fwrite(STDERR, "Option invalide ignorée -- $subject" . PHP_EOL);
                    }
                    else {
                        throw new Exception("Option invalide -- $subject");
                    }
            }
        }
    }

    /**
     * Retourne le tableau des arguments positionnel
     * 
     * @return array
     */
    public static function positional(): array
    {
        return self::$positional;
    }

    /**
     * Retourne le tableau des options invalides
     * 
     * @return array
     */
    public static function invalidOptions(): array
    {
        return self::$invalidOptions;
    }

}
