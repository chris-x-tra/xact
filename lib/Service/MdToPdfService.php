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

        private $format          ;
        private $margin_left     ;
        private $margin_right    ;
        private $margin_top      ;
        private $margin_bottom   ;
        private $showWindowGuide ;
        private $showFoldMarks   ;
        private $showFooter      ;

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

                $config = \OC::$server->get(\OCP\IConfig::class);

                // Liest Werte aus config/config.php mit Fallbacks:
                $this->format          = $config->getSystemValue('xact_format', 'A4');
                $this->margin_left     = $config->getSystemValue('xact_margin_left', 20);
                $this->margin_right    = $config->getSystemValue('xact_margin_right', 20);
                $this->margin_top      = $config->getSystemValue('xact_margin_top', 20);
                $this->margin_bottom   = $config->getSystemValue('xact_margin_bottom', 20);
                $this->showWindowGuide = $config->getSystemValue('xact_show_window_guide', true);
                $this->showFoldMarks   = $config->getSystemValue('xact_show_fold_marks', true);
                $this->showFooter      = $config->getSystemValue('xact_show_footer', true);
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
			'mode' => $this->mode,
			'format' => $this->format,
			'margin_left' => $this->margin_left,
			'margin_right' => $this->margin_right,
			'margin_top' => $this->margin_top,
			'margin_bottom' => $this->margin_bottom,
			'tempDir' => $tempDir,
		]);


                // Faltmarken + DIN 5008 Fensterkreuze in einem einzigen SVG
                $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm">';

                if ($this->showFoldMarks) {
                    $svgContent .= '
                    <!-- Faltmarken links -->
                    <g stroke="#999999" stroke-width="0.33mm">
                        <line x1="5mm" y1="105mm" x2="8.5mm" y2="105mm"/>
                        <line x1="5mm" y1="210mm" x2="8.5mm" y2="210mm"/>
                    </g>';
                }

                if ($this->showWindowGuide) {
                    $svgContent .= '
                    <!-- DIN 5008 Sichtfenster (Hellgraue Kreuze) -->
                    <g stroke="#cccccc" stroke-width="0.25mm">
                        <!-- Ecke Links-Oben (20mm, 55mm) -->
                        <line x1="17mm" y1="55mm" x2="23mm" y2="55mm"/><line x1="20mm" y1="52mm" x2="20mm" y2="58mm"/>
                        <!-- Ecke Rechts-Oben (110mm, 55mm) -->
                        <line x1="107mm" y1="55mm" x2="113mm" y2="55mm"/><line x1="110mm" y1="52mm" x2="110mm" y2="58mm"/>
                        <!-- Ecke Links-Unten (20mm, 100mm) -->
                        <line x1="17mm" y1="100mm" x2="23mm" y2="100mm"/><line x1="20mm" y1="97mm" x2="20mm" y2="103mm"/>
                        <!-- Ecke Rechts-Unten (110mm, 100mm) -->
                        <line x1="107mm" y1="100mm" x2="113mm" y2="100mm"/><line x1="110mm" y1="97mm" x2="110mm" y2="103mm"/>
                    </g>';
                }

                $svgContent .= '</svg>';
                $svgBase64 = 'data:image/svg+xml;base64,' . base64_encode($svgContent);

                $firstPageStyle = '';
                if ($this->showFoldMarks || $this->showWindowGuide) {
                    $firstPageStyle = "
                    <style>
                        @page :first {
                            background-image: url('$svgBase64');
                            background-repeat: no-repeat;
                            background-position: top left;
                        }
                    </style>";
                }

                // Right-aligned page number with total pages
                if ($this->showFooter) {
                    if (!empty($filename)) 
                            $filename = $filename . " - ";
                    $mpdf->SetHTMLFooter('<div style="text-align: right; font-weight: normal; font-style: normal; font-size: 8pt;">'
                            . $filename . ' Seite {PAGENO} von {nbpg} </div> ');
                 }

                $styledHtml = $firstPageStyle . $this->wrapHtml($html);
		if (DEBUG) {
			$myfile = fopen("/tmp/tst3.txt", "w");
			fwrite($myfile, $styledHtml);
			fclose($myfile);
		}
		$mpdf->WriteHTML($styledHtml);

		return $mpdf->Output('', 'S');
	}

	private function wrapHtml(string $html): string {
		$return = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
h1 { font-size: 22pt; border-bottom: 2px solid #eee; padding-bottom: 8px; }
h2 { font-size: 18pt; border-bottom: 1px solid #eee; padding-bottom: 4px; }
h3 { font-size: 14pt; }
code { background: #f5f5f5; border: 1px solid #e0e0e0; padding: 2px 4px; font-size: 10pt; border-radius: 3px; }
pre { background: #f5f5f5; border: 1px solid #e0e0e0; padding: 12px; overflow-x: auto; border-radius: 4px; }
pre code { border: none; padding: 0; background: none; }

table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f5f5f5; }
a { color: #0066cc; }
ol, ul { margin: 0 0 10px; padding-left: 24px; }
hr { border: none; border-top: 1px solid #eee; margin: 20px 0; }
img { max-width: 100%; }

body {
    margin:0;
    font-family: "OfficinaSanITC", "ITC Officina Sans", "ITC Officina Sans", sans-serif;
/* font-size: 12pt */
}
/* align left */
h3 {
    font-family: "OfficinaSanITC", "ITC Officina Sans", "ITC Officina Sans", sans-serif;
    font-size: 12pt;
    text-align: left;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
/* align center */
h4 {
    font-family: "OfficinaSanITC", "ITC Officina Sans", "ITC Officina Sans", sans-serif;
    font-size: 12pt;
    text-align: center;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
/* align right */
h5 {
    font-family: "OfficinaSanITC", "ITC Officina Sans", "ITC Officina Sans", sans-serif;
    font-size: 12pt;
    text-align: right;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
/* small */
h6 {
    font-family: "OfficinaSanITC", "ITC Officina Sans", "ITC Officina Sans", sans-serif;
    font-size: 8pt;
    font-weight: normal;
    margin: 0;
    padding: 0;
}
strong {
    font-family: "OfficinaSanITCBol", "ITC Officina Sans Bol", "ITC Officina Sans", sans-serif;
}
em {
    font-family: "OfficinaSanITCIta"
}
ul, ol {
    padding-left: 20pt
}
blockquote {
    border-inline-start: none;
    color: black;
    margin-left: 20pt;
    font-family: "OfficinaSanITCIta"
}
</style>

</head>
<body>' . $html . '</body>
</html>';

        	return $return;
	}
}
