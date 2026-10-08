<?php

namespace Tests\Unit;

use App\Support\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function test_busca_ignora_acentos_e_maiusculas(): void
    {
        $this->assertSame('acoes de inovacao em capinzal', Text::searchable('Ações de  INOVAÇÃO', 'em Capinzal'));
    }

    public function test_numeros_no_formato_brasileiro(): void
    {
        $this->assertSame('1.234', Text::number(1234));
        $this->assertSame('1.234,50', Text::number('1234.5'));
        $this->assertSame('', Text::number(null));
    }
}
