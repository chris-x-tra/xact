<?php
declare(strict_types=1);
namespace OCA\Xact\Service;

define('DEBUG', false);

require_once __DIR__ . '/PilcrowToBreakParser.php';
require_once __DIR__ . '/PagebreakParser.php';
require_once __DIR__ . '/CustomShortcutsExtension.php';

use OCA\Xact\Service\CustomShortcutsExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\CommonMarkConverter;

use Mpdf\Mpdf;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserSession;


class MdToPdfService {
	private IRootFolder $rootFolder;
	private IUserSession $userSession;
	private IL10N $l;

	public function __construct(
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IL10N $l,
	) {
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->l = $l;
	}

	public function convert(int $fileId): File {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \Exception($this->l->t('Not logged in'));
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$nodes = $userFolder->getById($fileId);

		if (empty($nodes)) {
			throw new \Exception($this->l->t('File not found'));
		}

		$file = $nodes[0];
		if (!($file instanceof File)) {
			throw new \Exception($this->l->t('Node is not a file'));
		}

		$mimeType = $file->getMimeType();
		if (!in_array($mimeType, ['text/markdown', 'text/x-markdown'], true)) {
			throw new \Exception($this->l->t('File is not a markdown file'));
		}

		$content = $file->getContent();
		$parentFolder = $file->getParent();
		$pdfFileName = pathinfo($file->getName(), PATHINFO_FILENAME) . '.pdf';
		if (DEBUG) {
			$myfile = fopen("/tmp/tst1.txt", "w");
			fwrite($myfile, $content);
			fclose($myfile);
		}
		$html = $this->markdownToHtml($content);
		if (DEBUG) {
			$myfile = fopen("/tmp/tst2.txt", "w");
			fwrite($myfile, $html);
			fclose($myfile);
		}
		$pdf = $this->htmlToPdf($html, $pdfFileName);

		if ($parentFolder->nodeExists($pdfFileName)) {
			$parentFolder->get($pdfFileName)->delete();
		}

		return $parentFolder->newFile($pdfFileName, $pdf);
	}

	private function markdownToHtml(string $markdown): string {
		$config = [
		    'html_input' => 'allow',
		    'allow_unsafe_links' => false,
				'renderer' => [
				    'block_separator' => "\n",
				    'inner_separator' => "\n",
				    'soft_break'      => "<br>\n",
				],

		];
		$environment = new Environment($config);
		$environment->addExtension(new CommonMarkCoreExtension());
		$environment->addExtension(new CustomShortcutsExtension());
		$converter = new MarkdownConverter($environment);
		return $converter->convert($markdown)->getContent();
	}

	private function htmlToPdf(string $html, string $filename = ''): string {
		$tempDir = sys_get_temp_dir() . '/xact';
		if (!is_dir($tempDir)) {
			mkdir($tempDir, 0755, true);
		}

		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'format' => 'A4',
			'margin_left' => 20,
			'margin_right' => 20,
			'margin_top' => 20,
			'margin_bottom' => 20,
			'tempDir' => $tempDir,
		]);

                // Right-aligned page number with total pages
		if (!empty($filename)) 
			$filename = $filename . " - ";
		$mpdf->SetHTMLFooter('
		    <div style="text-align: right; font-weight: normal; font-style: normal; font-size: 8pt;">'
			. $filename . ' Seite {PAGENO} von {nbpg}
		    </div> ');

		$styledHtml = $this->wrapHtml($html);
		$mpdf->WriteHTML($styledHtml);

		return $mpdf->Output('', 'S');
	}

	private function wrapHtml(string $html): string {
		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
<!-- style from original md2pdf - modified -->
    <style>
        h1 { font-size: 22pt; border-bottom: 2px solid #eee; padding-bottom: 8px; }
        h2 { font-size: 18pt; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        h3 { font-size: 14pt; }
        code { background: #f5f5f5; border: 1px solid #e0e0e0; padding: 2px 4px; font-size: 10pt; border-radius: 3px; }
        pre { background: #f5f5f5; border: 1px solid #e0e0e0; padding: 12px; overflow-x: auto; border-radius: 4px; }
        pre code { border: none; padding: 0; background: none; }
        blockquote { border-left: 4px solid #ddd; padding-left: 16px; margin-left: 0; color: #666; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        img { max-width: 100%; }
        a { color: #0066cc; }
        ul, ol { margin: 0 0 10px; padding-left: 24px; }
        hr { border: none; border-top: 1px solid #eee; margin: 20px 0; }
    </style>

<!-- Style extended by https://maennig.de/briefe-markdown -->
<style>
body {
    margin:0;
    font-family: "OfficinaSanITCBoo";
    font-size: 10pt
}
/* align left */
h3 {
    font-family: "OfficinaSanITCBol";
    text-align: left;
    font-size: 10pt;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
/* align center */
h4 {
    font-family: "OfficinaSanITCBol";
    text-align: center;
    font-size: 10pt;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
/* align right */
h5 {
    font-family: "OfficinaSanITCBol";
    text-align: right;
    font-size: 10pt;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
/* small */
h6 {
    font-family: "RotisSansSerif";
    font-size: 8pt;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
strong {
    font-family: "OfficinaSanITCBol"
}
em {
    font-family: "OfficinaSanITCBooIta"
}
ul, ol {
    padding-left: 20pt
}
blockquote {
    margin-left: 20pt;
    font-family: "OfficinaSanITCBooIta"
}
</style>

</head>
<body>' . $html . '</body>
</html>';
	}
}
