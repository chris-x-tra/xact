<?php

declare(strict_types=1);

namespace OCA\Xact\Service;

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

		$html = $this->markdownToHtml($content);
		$pdf = $this->htmlToPdf($html);

		if ($parentFolder->nodeExists($pdfFileName)) {
			$parentFolder->get($pdfFileName)->delete();
		}

		return $parentFolder->newFile($pdfFileName, $pdf);
	}

	private function markdownToHtml(string $markdown): string {
		$converter = new CommonMarkConverter([
			'html_input' => 'strip',
			'allow_unsafe_links' => false,
		]);
		return $converter->convert($markdown)->getContent();
	}

	private function htmlToPdf(string $html): string {
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

		$styledHtml = $this->wrapHtml($html);
		$mpdf->WriteHTML($styledHtml);

		return $mpdf->Output('', 'S');
	}

	private function wrapHtml(string $html): string {
		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; line-height: 1.6; color: #333; }
        h1 { font-size: 24pt; color: #111; border-bottom: 2px solid #eee; padding-bottom: 8px; }
        h2 { font-size: 18pt; color: #222; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        h3 { font-size: 14pt; color: #333; }
        h4 { font-size: 12pt; color: #444; }
        p { margin: 0 0 10px; }
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
</head>
<body>' . $html . '</body>
</html>';
	}
}
