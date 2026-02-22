<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait JsonValidationTrait
{
    private function validateAndDecodeJson(Request $request): array
    {
        $content = $request->getContent();

        if (trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new BadRequestHttpException('JSON must be an object');
        }

        return $data;
    }

    private function validateDto(object $dto, ValidatorInterface $validator): void
    {
        $violations = $validator->validate($dto);
        if ($violations->count() === 0) {
            return;
        }

        $messages = [];
        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath();
            $messages[] = $path !== '' ? $path . ': ' . $violation->getMessage() : $violation->getMessage();
        }

        throw new BadRequestHttpException(implode('; ', $messages));
    }

    private function assertValidUuid(string $id): void
    {
        if (!Uuid::isValid($id)) {
            throw new BadRequestHttpException('Invalid UUID format');
        }
    }

    private function normalizeIsoDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $formats = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('c');
            }
        }

        throw new BadRequestHttpException('Invalid date format, expected ISO-8601');
    }

    private function handleDomainException(\Throwable $e): JsonResponse
    {
        if ($e instanceof \DomainException) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($e instanceof BadRequestHttpException) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'error' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
