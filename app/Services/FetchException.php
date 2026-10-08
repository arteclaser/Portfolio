<?php

namespace App\Services;

use RuntimeException;

/** Falha ao consultar um endereço externo. A mensagem é exibível à equipe. */
class FetchException extends RuntimeException {}
