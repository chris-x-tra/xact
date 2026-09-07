import { defineConfig } from 'vite'

// Bewusst ohne @nextcloud/vite-config: für eine so minimale App reicht ein
// einfaches IIFE-Bundle, das Nextcloud per Util::addScript() als klassisches
// <script>-Tag lädt. Alle Abhängigkeiten (z. B. @nextcloud/files) werden
// mit ins Bundle gepackt, es muss also nichts extern geladen werden.
export default defineConfig({
	build: {
		outDir: 'js',
		emptyOutDir: true,
		sourcemap: true,
		rollupOptions: {
			input: 'src/main.js',
			output: {
				format: 'iife',
				entryFileNames: 'xact-main.js',
				// Assets (falls später CSS o.ä. dazukommt) mit reindeuten
				assetFileNames: 'xact-main.[ext]',
			},
		},
	},
})
