<?php

include('Output.php');

class Logo {

    public static function startLogo($lite = true) {
        if ($lite) {
            self::startLogoLite();
            return;
        }

        Output::intro();
        Output::print_msg("+--------------------------------------------------------------------------------+");
        Output::print_msg("|                                                                                |");
        Output::print_msg("|                                           .( (()  ) )(  )\                     |");
        Output::print_msg("|                                                       \( ( (())                |");
        Output::print_msg("|                                                              (())              |");
        Output::print_msg("|                          -==========.      _._        __      ___              |");
        Output::print_msg("|           __________      \_________|-----/   \-----+|  |+---+] [+--._O        |");
        Output::print_msg("|     _____/          \_ /| | |_|_|   |------ª-----ª-----ª------ª------|[]\      |");
        Output::print_msg("|    |  M  I  K  A  D  O  |¦|   |_|_______|    __           __         |[]])     |");
        Output::print_msg("|    |____________________|/ \_1937___ = - ===/============/==========.\\\\==\\\\_   |");
        Output::print_msg("|     (  )-(  )  (  )-(  ) ^-(  )(  )/  (( @--Y--@ ))(( @--Y--@ ))  (  )(  )=/   |");
        Output::print_msg("|   ---`´---`´----`´---`´-----`´--`´-----`---´ `--- ´-`---´ `---´ -- `´--`´----  |");
        Output::print_msg("|                                                                                |");
        Output::print_msg("|   \"Be like a train; go in the rain, go in the sun, go in the storm, go in the  |");
        Output::print_msg("|   dark tunnels! Be like a train; concentrate on your road and go with no       |");
        Output::print_msg("|   hesitation!\"                                                                 |");
        Output::print_msg("|                                                           Mehmet Murat ildan.  |");
        Output::print_msg("|                                                                                |");
        Output::print_msg("+--------------------------------------------------------------------------------+");
        Output::print_msg("");
    }

    public static function endLogo($lite = true) {
        if ($lite) {
            self::endLogoLittle();
            return;
        }

        Output::intro();
        Output::print_msg("   ____      ____      ____             ___________________  ");
        Output::print_msg("  |.  .|    |.  .|    |.  .|     |\____|     |             | ");
        Output::print_msg("  ===============================| ____|     |  E  N  D    | ");
        Output::print_msg("  |    |    |    |    |    |     |/    |     |             | ");
        Output::print_msg("  |    |    |    |    |    |           |     |    O F      | ");
        Output::print_msg("  |    |    |    |    |    |           |     |             | ");
        Output::print_msg("  |    |    |    |    |    |           |     |  T  H  E    | ");
        Output::print_msg("  |    |    |    |    |    |     |\____|     |             | ");
        Output::print_msg("  ===============================| ____|     | S C R I P T | ");
        Output::print_msg("  |.  .|    |.  .|    |.  .|     |/    |_____|_____________| ");
        Output::print_msg("   ----      ----      ----                                  ");
        Output::intro();
    }

    public static function startLogoLite()
    {
        Output::intro();
        Output::print_msg(" ______         ~~~                   ");
        Output::print_msg(" \__|_|___■■__=___||_.                ");
        Output::print_msg("  | / M  I  K  A  D  O)               ");
        Output::print_msg("  \o-( ).( ).( ).( )-o\\\ _._._._._._._");
        Output::intro();
    }

    public static function endLogoLittle()
    {
        Output::intro();
        Output::print_msg("                                       ");
        Output::print_msg("                          ___   END    ");
        Output::print_msg("                       D=|   \  OF     ");
        Output::print_msg(" _._._._._._._._._._._.  |____\ SCRIPT ");
        Output::intro();
    }
}

