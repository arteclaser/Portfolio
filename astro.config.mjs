import { defineConfig } from 'astro/config';

// Site estático: cada página é um arquivo HTML pronto, servido direto pela hospedagem.
export default defineConfig({
  site: 'https://mostraqui.net',
  trailingSlash: 'always',
  build: {
    format: 'directory',
    assets: 'assets',
    // Nenhum estilo ou script embutido no HTML: a política de segurança só aceita arquivos do próprio site.
    inlineStylesheets: 'never',
  },
  vite: {
    build: { assetsInlineLimit: 0 },
  },
  devToolbar: { enabled: false },
});
