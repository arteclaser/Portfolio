# Cópias de segurança e restauração

## O que é copiado

`php artisan mostraqui:backup` gera **um único arquivo `.zip`** com:

| Conteúdo | Pasta no .zip |
| --- | --- |
| Todas as tabelas do banco (um arquivo JSON por tabela), exceto sessões, cache e tokens temporários | `database/*.jsonl` |
| Todas as imagens e PDFs enviados (variantes otimizadas) | `media/` |
| Data, versão do sistema e contagem de registros | `manifest.json` |

O comando não depende de `mysqldump`, que pode não estar disponível em hospedagem compartilhada.
O `.env` **não** é copiado, porque contém senhas: guarde-o à parte, em local seguro.

## Onde ficam e por quanto tempo

- Pasta padrão: `~/mostraqui-app/storage/app/backups/` (fora da pasta pública, permissão 640).
- `--manter=14` mantém as 14 cópias mais recentes e apaga as mais antigas.
- `--destino=/outra/pasta` grava em outro lugar.

## Rotina recomendada

1. **Diária, automática:** Cron às 03:00 (ver `IMPLANTACAO-HOSTGATOR.md`, passo 8).
2. **Semanal, fora do servidor:** baixe a cópia mais recente pelo Gerenciador de arquivos e guarde-a
   em outro local. Uma cópia que fica só no mesmo servidor não protege contra a perda da conta.
3. **Complementar:** o backup da própria hospedagem (cPanel → Backup) também cobre arquivos e banco.
   Confira a retenção oferecida pelo seu plano.
4. **Teste a restauração** pelo menos uma vez por semestre, em uma instalação de testes.

## Como restaurar

> A restauração **substitui todos os dados atuais** pelos da cópia.

```bash
cd ~/mostraqui-app
php artisan down                      # coloca o site em manutenção
php artisan mostraqui:backup          # cópia do estado atual, por precaução
php artisan mostraqui:restaurar storage/app/backups/backup-AAAAMMDD-HHMMSS.zip
php artisan up
```

O comando:

1. confere se o arquivo é uma cópia deste sistema;
2. atualiza o esquema do banco (`migrate`) sem apagar tabelas;
3. esvazia as tabelas e reinsere os registros na ordem das chaves estrangeiras;
4. substitui a pasta de mídia pelos arquivos da cópia.

Depois de restaurar, as sessões continuam válidas. Para encerrar todas, apague os arquivos de
`storage/framework/sessions/`.

### Sem terminal

Crie um Cron que rode **uma única vez** (ajuste o horário para poucos minutos à frente) com
`... php artisan mostraqui:restaurar CAMINHO_DO_ZIP --forcar`, e remova o Cron depois que ele rodar.

## Verificação feita no desenvolvimento

O teste `SecurityAndIntegrityTest::test_backup_and_restore_roundtrip` cria conteúdo e uma imagem,
gera a cópia, apaga os dados, restaura e confere que o conteúdo e o arquivo voltaram. O mesmo
ciclo foi ensaiado em MariaDB 10.11, na estrutura de pastas do cPanel.
