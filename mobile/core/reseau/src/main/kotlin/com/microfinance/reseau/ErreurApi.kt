package com.microfinance.reseau

/** Erreurs de l'API traduites pour l'écran ; aucune exception réseau ne dépasse le dépôt. */
sealed interface ErreurApi {
    val message: String

    data object Reseau : ErreurApi {
        override val message = "Pas de connexion au serveur."
    }

    data object NonAutorise : ErreurApi {
        override val message = "Session expirée : reconnectez-vous."
    }

    /** 403 avec un code métier : appareil_en_attente, appareil_revoque, appareil_non_autorise, mot_de_passe_a_changer… */
    data class Interdit(val code: String?, override val message: String) : ErreurApi

    data class Validation(val champs: Map<String, String>, override val message: String) : ErreurApi

    data object TropDeTentatives : ErreurApi {
        override val message = "Trop de tentatives : réessayez dans une minute."
    }

    data class Serveur(val statut: Int) : ErreurApi {
        override val message = "Le serveur n'a pas pu répondre ($statut)."
    }
}

sealed interface Resultat<out T> {
    data class Succes<T>(val valeur: T) : Resultat<T>

    data class Echec(val erreur: ErreurApi) : Resultat<Nothing>
}
