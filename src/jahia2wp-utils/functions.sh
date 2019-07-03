#!/bin/bash

# Cette fonction prend en paramètre une commande à exécuter (elle se retrouve dans le paramètre $1).
# #La commande est exécutée et ensuite, un contrôle du code de retour de l'exécution est effectué.
# Ce code se trouve dans la variable $?.
# S'il est différent de 0, c'est qu'il y a eu une erreur. Dans ce cas-là, on affiche un message en
# rouge et le script se termine.
function execCmd
{
    # Exécution de la commande
    $1

    # Si une erreur est survenue
    if [ `echo $?` != "0" ]
    then
        echo -e "\033[0;31mError executing following command. Have a look at above error \033[0m"
        # On réaffiche la commande qui a posé problème
        echo $1
        exit 1
    fi

}