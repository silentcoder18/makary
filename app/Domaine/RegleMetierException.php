<?php

namespace App\Domaine;

use RuntimeException;

/** Une règle du cahier des charges (§7, §8) empêche l'opération demandée ; le message s'adresse à l'agent. */
class RegleMetierException extends RuntimeException {}
