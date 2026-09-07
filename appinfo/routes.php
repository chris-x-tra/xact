<?php

declare(strict_types=1);

return [
	'routes' => [
		[
			'name' => 'Convert#convert',
			'url' => '/convert/{fileId}',
			'verb' => 'POST',
			'requirements' => ['fileId' => '\d+'],
		],
	],
];
