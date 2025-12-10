<?php

namespace App\Http\Controllers\V1;

use App\Actions\User\InvitedUser\CreateInvitedUserAction;
use App\Actions\User\InvitedUser\ImportInvitedUsersFromFileAction;
use App\Actions\User\InvitedUser\ShowInvitedUserAction;
use App\Attributes\RequiresPermission;
use App\DTOs\User\CreateInvitedUserDTO;
use App\Enums\IDP\PermissionDefaults;
use App\Exceptions\RoleManagement\UnauthorizedRoleAssignmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportInvitedUsersFormRequest;
use App\Http\Requests\InvitedUserFormRequest;
use App\Http\Resources\User\InvitedUserResource;
use App\Models\User;
use App\Services\FileReaders\FileReaderFactory;
use App\Services\Models\InvitedUserService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

#[Group('User')]
class InvitedUserController extends Controller
{
    public function __construct(
        protected InvitedUserService $invitedUserService,
        protected ShowInvitedUserAction $showInvitedUserAction
    ) {}

    /**
     * List all invited users.
     */
    #[RequiresPermission(PermissionDefaults::READ_USER)]
    // Global search parameter
    #[QueryParameter('search', description: 'Global search across searchable fields: first_name, last_name, email, sirh_id. Minimum 2 characters required.', type: 'string', example: 'John')]
    public function index(): JsonResponse
    {
        try {
            $invitedUsers = $this->invitedUserService->all(15, 1, ['financers']);

            return response()->json([
                'data' => $invitedUsers,
                'message' => 'Invited users retrieved successfully',
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get an invited user by UUID
     *
     * @group Core/InvitedUsers
     */
    #[RequiresPermission(PermissionDefaults::READ_USER)]
    public function show(string $uuid): InvitedUserResource|JsonResponse
    {
        try {
            $result = $this->showInvitedUserAction->execute($uuid);

            return new InvitedUserResource($result['user']);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invited user not found',
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an invited user
     *
     * @group Core/InvitedUsers
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_USER)]
    public function update(InvitedUserFormRequest $request, string $uuid): JsonResponse
    {
        try {
            // Find the invited user
            $invitedUser = $this->invitedUserService->find($uuid);

            // Update the invited user with validated data
            $updatedUser = $this->invitedUserService->update($invitedUser, $request->validated());

            return response()->json([
                'data' => $updatedUser,
                'message' => 'Invited user updated successfully',
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invited user not found',
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete an invited user
     *
     * @group Core/InvitedUsers
     */
    #[RequiresPermission(PermissionDefaults::DELETE_USER)]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $invitedUser = $this->invitedUserService->find($uuid);

            $this->invitedUserService->delete($invitedUser);

            return response()->json([
                'message' => 'Invited user deleted successfully',
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invited user not found',
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new invited user
     *
     * @group Core/InvitedUsers
     */
    #[RequiresPermission(PermissionDefaults::CREATE_USER)]
    public function store(InvitedUserFormRequest $request): JsonResponse
    {
        try {
            // Get the validated data
            $validatedData = $request->validated();

            // Get authenticated user as inviter
            $inviter = auth()->user();
            if (! $inviter instanceof User) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Create DTO from validated data
            $dto = CreateInvitedUserDTO::from($validatedData);

            // Create action instance with DTO
            $action = new CreateInvitedUserAction($dto);

            // Execute with role validation
            $invitedUser = $action->withRoleValidation($inviter)->execute();

            return response()->json([
                'data' => $invitedUser,
                'message' => 'Invited user created successfully',
            ], Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UnauthorizedRoleAssignmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Invalid request',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import users from a file (CSV, XLS, XLSX)
     *
     * @group Core/InvitedUsers
     */
    #[RequiresPermission(PermissionDefaults::CREATE_USER)]
    public function import(ImportInvitedUsersFormRequest $request): JsonResponse
    {
        try {
            // Get the validated data
            $validatedData = $request->validated();

            // Get the file and financer ID
            $file = $validatedData['file'] ?? $validatedData['csv_file'];

            $financerId = $validatedData['financer_id'];

            // Ensure the parameters are of the correct type
            if (! $file instanceof UploadedFile) {
                throw new InvalidArgumentException('File must be an instance of UploadedFile');
            }

            if (! is_string($financerId)) {
                throw new InvalidArgumentException('Financer ID must be a string');
            }

            // Validate file headers before storing
            $fileValidation = $this->validateFileHeaders($file);
            if (! $fileValidation['valid']) {
                return response()->json([
                    'message' => 'Invalid file structure',
                    'error' => $fileValidation['error'],
                    'missing_headers' => $fileValidation['missing_headers'] ?? [],
                    'found_headers' => $fileValidation['found_headers'] ?? [],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Store the file temporarily using UploadedFile's store method
            // Use same disk logic as action (s3-local for local/testing, s3 for production)
            $disk = app()->environment(['local', 'testing']) ? 's3-local' : 's3';
            $filePath = $file->store('imports', $disk);

            if ($filePath === false) {
                return response()->json([
                    'message' => 'Failed to store file',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Get the current user ID
            $currentUser = auth()->user();
            if (! $currentUser instanceof User) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }
            $userId = (string) $currentUser->id;

            // Generate import ID
            $importId = Str::uuid()->toString();

            // Dispatch the import job to the queue
            ImportInvitedUsersFromFileAction::dispatch($filePath, $financerId, $userId, $importId);

            return response()->json([
                'data' => [
                    'message' => 'Import job has been queued for processing',
                    'import_id' => $importId,
                    'file_path' => $filePath,
                    'financer_id' => $financerId,
                ],
                'message' => 'File import has been queued successfully. You will receive updates via websocket.',
            ], Response::HTTP_ACCEPTED);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred during import',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate file headers (CSV, XLS, XLSX)
     */
    /**
     * @return array<string, mixed>
     */
    private function validateFileHeaders(UploadedFile $file): array
    {
        $requiredHeaders = ['first_name', 'last_name', 'email'];

        try {
            // Use same disk logic as action (s3-local for local/testing, s3 for production)
            $disk = app()->environment(['local', 'testing']) ? 's3-local' : 's3';
            $tempPath = $file->store('temp-validation', $disk);

            if ($tempPath === false) {
                return [
                    'valid' => false,
                    'error' => 'Failed to store file for validation',
                ];
            }

            try {
                // Use FileReaderFactory to read and validate
                $factory = new FileReaderFactory;
                $reader = $factory->createFromFile(
                    $tempPath,
                    $file->getClientMimeType()
                );

                // Read first few rows to get headers
                $result = $reader->readAndValidate($tempPath);

                // Clean up temp file
                Storage::disk($disk)->delete($tempPath);

                if ($result['error'] !== null) {
                    return [
                        'valid' => false,
                        'error' => $result['error'],
                    ];
                }

                // Extract headers from first row if we have data
                if (empty($result['rows'])) {
                    return [
                        'valid' => false,
                        'error' => 'File contains no data rows',
                    ];
                }

                // Get headers from first row keys
                $headers = array_keys($result['rows'][0]);

                // Check for required headers
                $missingHeaders = array_diff($requiredHeaders, $headers);

                if ($missingHeaders !== []) {
                    return [
                        'valid' => false,
                        'error' => 'Missing required headers: '.implode(', ', $missingHeaders),
                        'missing_headers' => array_values($missingHeaders),
                        'found_headers' => $headers,
                    ];
                }

                return [
                    'valid' => true,
                    'headers' => $headers,
                ];

            } catch (InvalidArgumentException $e) {
                // Unsupported file format
                Storage::disk($disk)->delete($tempPath);

                return [
                    'valid' => false,
                    'error' => $e->getMessage(),
                ];
            }

        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'Error reading file: '.$e->getMessage(),
            ];
        }
    }
}
