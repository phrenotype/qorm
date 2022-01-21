<?php

namespace Q\Orm\Cli;

const Q_LOGO = <<<STR
 #####                       
#     #  ####  #####  #    # 
#     # #    # #    # ##  ## 
#     # #    # #    # # ## # 
#   # # #    # #####  #    # 
#    #  #    # #   #  #    # 
 #### #  ####  #    # #    # 
STR;

class Bin
{

    private $registry = [];

    public function __construct()
    {
    }

    function __get($name)
    {
        return $this->$name;
    }

    private function commands()
    {        
        $usage = self::color("Usage", FG::BROWN, BG::BLACK) . PHP_EOL;
        $usage .= "command [arguments]" . PHP_EOL . PHP_EOL;

        $commands = self::color("Available commands", FG::BROWN, BG::BLACK) . PHP_EOL;

        $formatCommand = function ($command) {
            return self::color(sprintf("%-30s", $command), FG::GREEN, BG::BLACK);
        };

        $formatDesc = function ($text) {
            return self::color($text, FG::LIGHT_GRAY, BG::BLACK);
        };

        $commandString = $formatCommand("makemigrations") . $formatDesc("Detects changes in models and generates a migration file based on those changes.") . PHP_EOL;

        $commandString .= $formatCommand("migrations") . $formatDesc("Lists all the migrations and their applied status.") . PHP_EOL;

        $commandString .= $formatCommand("migrate") . $formatDesc("Applies the last unapplied migration.") . PHP_EOL;
        $commandString .= $formatCommand("migrate [MigrationName]") . $formatDesc("Applies all migrations down to that particular migration.") . PHP_EOL;

        $commandString .= $formatCommand("rollback") . $formatDesc("Undo the last applied migration.") . PHP_EOL;
        $commandString .= $formatCommand("rollback [MigrationName]") . $formatDesc("Rollback all migrations up to that particular migration.") . PHP_EOL;

        $commandString .= $formatCommand("inspect") . $formatDesc("Generates models based on existing database.") . PHP_EOL;

        $commandString .= $formatCommand("create [ModelName]") . $formatDesc("Generates a new model.");

        $commands .= $commandString . PHP_EOL;

        return ($usage . $commands);
    }

    public function run(array $args, callable $onRun = null)
    {
        if (php_sapi_name() === 'cli') {

            if (count($args) <= 1) {
                $header = self::color(Q_LOGO, FG::LIGHT_BLUE, BG::BLACK) . PHP_EOL . PHP_EOL;
                $version = self::color('Q Orm', FG::GREEN, BG::BLACK) . PHP_EOL . PHP_EOL;
                fwrite(STDOUT, $header . $version . $this->commands());
                die;
            }

            if ($onRun) {
                $onRun();
            }

            $arguments = array_slice($args, 1);
            $argsToString = trim(join(' ', $arguments));
            foreach ($this->registry as $command => $function) {
                if (preg_match("#^$command$#", $argsToString)) {
                    $function($arguments);
                    die;
                }
            }
            echo PHP_EOL;
            echo Bin::color("Unknown command", FG::RED, BG::LIGHT_GRAY) . PHP_EOL . PHP_EOL;
            echo $this->commands();
            die;
        }
    }

    public function register(string $regex, callable $function)
    {
        if ($this->registry[$regex] ?? false) {
            throw new \Error('Command Already Exists.');
        }
        $this->registry[$regex] = $function;
    }

    public static function color(string $text, string $fg, string $bg)
    {
        $fgMap = array_values(FG::getConstants());
        $bgMap = array_values(BG::getConstants());

        $string = '';
        if (in_array($bg, $bgMap) && in_array($fg, $fgMap)) {
            $string .= "\033[{$fg};{$bg}m{$text}";
        } else if (in_array($bg, $bgMap)) {
            $string .= "\033[{$bg}m{$text}";
        } else if (in_array($fg, $fgMap)) {
            $string .= "\033[{$fg}m{$text}";
        } else {
            throw new \Error("Invalid self::color(s).");
        }

        $string .= "\033[0m";

        return $string;
    }

    public static function line(string $line, $fg = FG::LIGHT_GRAY, $bg = BG::BLACK)
    {
        $string = PHP_EOL . self::color($line, $fg, $bg) . PHP_EOL;
        fwrite(STDOUT, $string);
        return true;
    }
}
