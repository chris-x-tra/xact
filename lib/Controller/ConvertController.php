<?php

declare(strict_types=1);

namespace OCA\Xact\Controller;

use OCA\Xact\Service\MdToPdfService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ConvertController extends Controller {
	private MdToPdfService $service;
	private LoggerInterface $logger;

	public function __construct(
		string $appName,
		IRequest $request,
		MdToPdfService $service,
		LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->logger = $logger;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function convert(int $fileId): DataResponse {
		try {
			$node = $this->service->convert($fileId);
			return new DataResponse([
				'status' => 'success',
				'data' => [
					'fileId' => $node->getId(),
					'name' => $node->getName(),
				],
			]);
		} catch (\Throwable $e) {
			$this->logger->error('xact md2pdf conversion failed', [
				'fileId' => $fileId,
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
			return new DataResponse(
				[
					'status' => 'failure',
					'data' => [
						'message' => $e->getMessage(),
					],
				],
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
	}
}
