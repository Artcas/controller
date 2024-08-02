<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\AcknowledgeChangeRequest;
use App\Http\Requests\Jobs\BulkUpdate;
use App\Http\Requests\Jobs\ManuallyAssocRequest;
use App\Http\Requests\Jobs\OpenServicePo;
use App\Http\Requests\Jobs\PoCompareRequest;
use App\Http\Requests\Jobs\PullFromApi;
use App\Http\Requests\Jobs\ReturnJobRequest;
use App\Http\Requests\Jobs\SendNoteToHD;
use App\Http\Requests\Jobs\SingleJobRequest;
use App\Http\Requests\Jobs\StoreInstructionsRequest;
use App\Http\Requests\Jobs\StoreVoiceEmail;
use App\Http\Requests\Jobs\UpdateJobRequest;
use App\Models\Job;
use App\Models\JobLeadSafeDocument;
use App\Models\JobStatus;
use App\Repositories\Contracts\JobRepositoryInterface;
use App\Repositories\Contracts\JobStatusRepositoryInterface;
use App\Services\Contracts\HomeDepotServiceInterface;
use App\Services\JobNotes\JobNotesService;
use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class JobsController extends Controller
{
    protected $jobs;
    protected $jobStatuses;
    protected $homeDepotService;
    protected $notesService;

    public function __construct
    (
        JobRepositoryInterface       $jobRepository,
        HomeDepotServiceInterface    $homeDepotService,
        JobStatusRepositoryInterface $jobStatusRepository,
        JobNotesService              $notesService
    )
    {
        $this->jobs = $jobRepository;
        $this->homeDepotService = $homeDepotService;
        $this->jobStatuses = $jobStatusRepository;
        $this->notesService = $notesService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/jobs/",
     *     summary="Load Jobs",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="provider_id",
     *         in="query",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Provider",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Job Status",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="flag",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Job Flag",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="installer_id",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Installer Id",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="manager_id",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="District Manager",
     *         required=false,
     *     ),
     *      @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Store ID",
     *         required=false,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function returnJobs(ReturnJobRequest $request)
    {
        return $this->jobs->returnJobs(
            $request->get('provider_id'),
            $request->get('status'),
            $request->get('flag'),
            $request->get('filterValue'),
            $request->get('installer_id'),
            $request->get('manager_id'),
            $request->get('stores'),
            $request->get('page'),
            $request->get('perPage'),
            $request->get('action_date_start'),
            $request->get('action_date_end'),
            $request->get('orderField'),
            $request->get('orderDirection'),
            $request->get('show_archived'),
            $request->get('territory'),
            $request->get('has_sticky')
        );
    }


    public function returnFilteredJobs(Request $request)
    {
        return $this->jobs->returnJobsByFilter(
            $request->get('provider_id'),
            $request->get('filter'),
            $request->get('page'),
            $request->get('perPage'),
            $request->get('orderField'),
            $request->get('orderDirection'),
            $request->get('status'),
            $request->get('flag'),
            $request->get('filterValue'),
            $request->get('installer_id'),
            $request->get('stores'),
            $request->get('action_date_start'),
            $request->get('action_date_end'),
            $request->get('show_archived'),
            $request->get('territory'),
            $request->get('has_sticky')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/jobs/waiting-for-product",
     *     summary="Return waiting for product jobs",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="provider_id",
     *         in="query",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Provider",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function returnWaitingForProduct(Request $request)
    {
        return $this->jobs->returnWaitingForProductsJob
        (
            $request->get('provider_id'),
            $request->get('page'),
            $request->get('perPage'),
            $request->get('orderField'),
            $request->get('orderDirection'),
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/jobs/single",
     *     summary="Load Job Statuses",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *    @OA\Parameter(
     *         name="store_number",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Store Number",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="job_number",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Job Number",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function returnSingeJob(SingleJobRequest $request): \Illuminate\Http\JsonResponse
    {
        $job = $this->jobs->returnSingleJob($request->get('provider_id'), $request->get('store_number'), $request->get('job_number'));

        if (str_contains($job->job_number, 'SR'))
            $job->is_service_job = true;
        else
            $job->is_service_job = false;

        dispatch(new \App\Jobs\UserAction\TrackUserActions([
            'store_id' => $job->store_id,
            'job_id' => $job->id,
            'user_id' => $this->guard()->id(),
            'message' => 'Opened a PO',
            'action_date' => Carbon::now()->format('Y-m-d H:i:s')
        ]));

        return response()->json($job);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/jobs/single/{jobId}/original",
     *     summary="Get original job data",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *    @OA\Parameter(
     *         name="jobId",
     *         in="path",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Job Id",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function getSingleJobOriginalData($jobId)
    {
        $data = $this->jobs->getOriginalJobDataByJobId($jobId);
        if (!$data)
            return response()->json([]);
        return response()->json($data->data);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/jobs/single/{jobId}",
     *     summary="Update Current Job",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *    @OA\Parameter(
     *         name="job_next_action_date",
     *         in="query",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Next Action Date",
     *         required=true
     *     ),
     *    @OA\Parameter(
     *         name="schedule_arrival",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Schedule Arrival",
     *         required=false
     *     ),
     *      @OA\Parameter(
     *         name="change_reason_code",
     *         in="query",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Reshedule Reason Code",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="installer_id",
     *         in="query",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Installer ID",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="schedule_start",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Schedule Start Time",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="schedule_end",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Schedule End Time",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="status_label",
     *         in="query",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Job Status",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function updateJob($jobId, UpdateJobRequest $request): \Illuminate\Http\JsonResponse
    {
        $job = $this->jobs->find($jobId);
        $jobComments = $job->comments;
        $is_service_po = str_contains($job->job_number, 'SR');

        //Installer Changed Deleting Old Payroll Entry
        if ($job->installer_id != $request->get('installer_id')) {
            $this->deletePayrollEntry($jobId, $job->installer_id);

            $oldInstaller = \App\Models\User::select('first_name', 'last_name')->where('id', $job->installer_id)->first();
            $newInstaller = \App\Models\User::select('first_name', 'last_name')->where('id', $request->get('installer_id'))->first();

            $note['job_id'] = $jobId;
            $note['status_label'] = 'Installer Changed';
            $note['notes'] = 'Installer changed from ' . $oldInstaller->first_name . ' '  . $oldInstaller->last_name . ' to ' . $newInstaller->first_name . ' ' . $newInstaller->last_name;
            $note['user_name'] = $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name;

            \App\Models\JobNote::create($note);

            if (!$is_service_po)
                $this->deleteBlankDocuments($jobId, $job->installer_id);
        }

        $old = $job->getOriginal();
        $job->update($request->validated());
        $title = ($request->has('status_id') && $old['status_id'] != $request->get('status_id')) ? JobStatus::returnStatusLabel($request->get('status_id')) : 'Status Tab Changes';

        if ($request->get('status_id') == JobStatus::SCHEDULED || $request->get('status_id') == JobStatus::RESCHEDULED) {
            if (!\App\Models\Payroll::where('job_id', $jobId)->first())
                $this->createPayrollEntry($job);
            if ($request->get('status_id') == JobStatus::SCHEDULED) {

                dispatch(new \App\Jobs\UserAction\TrackUserActions([
                    'store_id' => $job->store->id,
                    'job_id' => $job->id,
                    'user_id' => $this->guard()->id(),
                    'message' => 'Scheduled a PO',
                    'action_date' => Carbon::now()->format('Y-m-d H:i:s')
                ]));

                dispatch(new \App\Jobs\Document\CreateBlank($jobId));
            }
        }

        if (!$is_service_po) {
            $dateChanged = Carbon::parse($old['job_next_action_date'])->format('Y-m-d') != Carbon::parse($request->get('job_next_action_date'))->format('Y-m-d');
            $hdResponse = $this->updateJobStatusOnHomeDepot($job, $request->get('status_id'), $request->get('change_reason_code'), $dateChanged);

            if($hdResponse['status'] == 404)
                return response()->json(['message' => 'PO was not updated on Home Depot'], 404);
        }

        $this->notesService->saveJobChangesToNotes($job->getChanges(), $old, $this->guard()->user(), $title, $jobId, $request->get('internal_comments'));

        if($request->has('installer_instructions')) {
            $this->updateInstructions($jobId, $request);
        }

        $this->updateCalendarEntry($job, $job->installer_id, $request->get('job_next_action_date'), $request);


        return response()->json($job);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/jobs/single/{jobId}/instructions",
     *     summary="Update Current Job Instructions",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *    @OA\Parameter(
     *         name="jobId",
     *         in="path",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Job Id",
     *         required=true
     *     ),
     *     @OA\RequestBody(
     *          description="Login Credentials",
     *          required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     description="Installer Instructions",
     *                     property="installer_instructions",
     *                     type="string",
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function updateJobInstructions(StoreInstructionsRequest $request, $jobId): JsonResponse
    {
        $this->updateInstructions($jobId, $request);

        return response()->json([]);
    }

    private function updateInstructions($jobId, $request)
    {
        $instructions = \App\Models\JobComment::where('job_id', $jobId)->first();
        $shouldCreate = false;

        if(!$instructions)
            $shouldCreate = true;

        if($instructions && $instructions->installer_instructions != $request->get('installer_instructions'))
            $shouldCreate = true;

        if(!empty($request->get('installer_instructions'))) {
            \App\Models\JobComment::updateOrCreate(
                [
                    'job_id' => $jobId
                ],
                [
                    'installer_instructions' => $request->get('installer_instructions')
                ]
            );

            if($shouldCreate) {
                $note['job_id'] = $jobId;
                $note['status_label'] = 'Instructions Changed';
                $note['notes'] = $request->get('installer_instructions');
                $note['user_name'] = $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name;

                \App\Models\JobNote::create($note);
            }
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/jobs/load-statuses",
     *     summary="Load Job Statuses",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function returnJobStatuses(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->jobStatuses->getJobStatuses());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/jobs/get-status-counts",
     *     summary="Load Jobs Status Numbers",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="provider_id",
     *         in="query",
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Provider",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function returnJobsStatusCounts(Request $request)
    {
        $fallback = Auth::user() ? Auth::user()->provider_id : 0;
        $provider = $request->has('provider') ? $request->get('provider') : $fallback;
        $territory = $request->has('territory') ? $request->get('territory') : 'all';
        return $this->jobs->returnJobsStatusCounts($provider, $territory);
    }

    public function pullFromApi($jobId, PullFromApi $request)
    {
        $job = $this->jobs->find($jobId);

        dispatch(new \App\Jobs\UserAction\TrackUserActions([
            'store_id' => $job->store->id,
            'job_id' => $job->id,
            'user_id' => $this->guard()->id(),
            'message' => 'Updated PO from THD',
            'action_date' => Carbon::now()->format('Y-m-d H:i:s')
        ]));

        if (!$job)
            return response()->json(['status' => 404, 'message' => __('api.job_not_exist')]);

        if ($job->provider_id == 1) {
            $this->updateJobDetails($job);
            //Updating Lead Safe
            $this->updateLeadSafeData($job);
        }
    }

    // TODO what its do.
    // If we are closing Measure job for HD need send response with [acknowledgeMeasureDiagram] to endpoint /poupdate/close/measure
    // If we are closing Install job for HD same endpoint with /poupdate/close/install params [ackFaxLienWaiver] = Y [jobPermitNumber] -> Needs update Migration or can be NA
    // Step 3 check ->wp-content/plugins/api_connector/_scripts/api006.php
    // Step 4 for Measure jobs  check -> wp-content/plugins/dragonfly_payroll/_includes/obj.v3payroll.php (calculated_payout)
    // Step 5 IF we need Action Tracker and report we need to store it.
    // Step 6 Archive Jobs set status Keyrec`d and completed_date
    // Step 7 Create internal note
    // Step 8 Send Note to the store
    // Step 9 For Measures we don`t have it  (dragonfly_nearby_measures) and all functionality is mystery for me but we need delete from there
    public function closeJob($jobId)
    {
        $job = $this->jobs->find($jobId);
        $service_job = !is_numeric($job->job_number);

        if ($job->provider_id == 1) {
            if($job->job_type == 'M' && !$service_job) {
                $response = $this->checkIfMeasureReadyToBeClosed($job);

                if (!$response)
                    return response()->json(['message' => __('api.close_po_measure')], 404);
            }else if (!$service_job) {
                $response = $this->checkIfInstallIsReadyToBeClosed($job);

                dispatch(new \App\Jobs\UserAction\TrackUserActions([
                    'store_id' => $job->store->id,
                    'job_id'   => $job->id,
                    'user_id'  => $this->guard()->id(),
                    'message'  => 'Closed a PO',
                    'action_date' => Carbon::now()->format('Y-m-d H:i:s')
                ]));

                if (!$response)
                    return response()->json(['message' => __('api.close_po_install')], 404);
            }

            return $this->processCloseHdJob($job);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/jobs/{jobId}/voicemail",
     *     summary="Store Voicemail",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="message",
     *         in="query",
     *         @OA\Schema(
     *             type="text"
     *         ),
     *         description="Message",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function storeVoiceEmail($jobId, StoreVoiceEmail $request)
    {
        $exists = \App\Models\VoiceEmail::where('job_id', '=', $jobId)->first();

        if ($exists) {
            $exists->update([
                'message' => $request->get('message')
            ]);
            return response()->json([]);
        } else {
            \App\Models\VoiceEmail::create([
                'job_id' => $jobId,
                'message' => $request->get('message')
            ]);

            \App\Models\JobNote::create([
                'job_id' => $jobId,
                'status_label' => 'Voicemail Created',
                'user_name' => $request->user()->first_name . ' ' . $request->user()->last_name,
                'notes' => $request->get('message')
            ]);
            return response()->json([]);
        }
    }

    //TODO ARTYOM: SWAGGER REPO ETC
    public function loadJobNotes($jobId)
    {
        $externalNotes = [];
        $job = $this->jobs->find($jobId);

        $search = [
            'poNumber'    => $job->job_number,
            'storeNumber' => $job->provider_store_number
        ];

        if (!str_contains($job->job_number, 'SR')) {
            $response = $this->homeDepotService->poNotesRetrieve($search);

            if ($response['status'] == 200)
                $externalNotes = $response['data']['note'];
        }

        return [
            'notes' => $job->notes()->orderBy('created_at', 'asc')->get(),
            'external_notes' => $externalNotes
        ];
    }

    public function returnPayPeriods()
    {
        $dates = $this->jobs->returnSubmittedDates();

        $response = [];
        $startPoint = $dates->first()->submitted_to_payroll_date;

        if ($startPoint >= Carbon::today()->subYear())
            $startPoint = Carbon::today()->subYear();

        $endPoint = Carbon::today()->addDay();
        $period = Carbon::create($startPoint)->daysUntil($endPoint);

        foreach ($period as $p) {
            if ($p->format('w') == 0) {
                $date = $p->format('m/d/Y') . '-' . $p->addWeek(2)->subDay(1)->format('m/d/Y');
                $response[] = [
                    'id' => $date,
                    'name' => $date
                ];
            }
        }

        $response = array_reverse($response);

        return response()->json($response);
    }

    public function bulkUpdate(BulkUpdate $request)
    {
        foreach ($request->get('jobIds') as $jobId) {
            $job = $this->jobs->find($jobId);

            if (str_contains($job->job_number, 'SR'))
                continue;

            $this->updateJobDetails($job);
            //Updating Lead Safe
            $this->updateLeadSafeData($job);
        }

        return response()->json([]);
    }

    public function openServicePo(OpenServicePo $request, $jobId)
    {
        $job = $this->jobs->find($jobId);
        $newJob = $job->replicate();
        $newJob->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $newJob->reason = $request->get('reason');
        $newJob->job_number = $this->defineServicePoNumber($job->job_number, $job->provider_store_number);
        $newJob->job_next_action_date = Carbon::now()->format('Y-m-d H:i:s');
        $newJob->related_measure_number = $job->job_number;
        $newJob->status_id = JobStatus::READY_TO_SCHEDULE;
        $newJob->po_cost_amount = 0;
        $newJob->is_archived = 0;
        $newJob->save();

        //Creating Internal Note
        dispatch(new \App\Jobs\Notes\StoreNote([
            'job_id'        => $job->id,
            'status_label'  => 'SR PO Created',
            'notes'         => $request->get('reason') . ' <b>' . $newJob->job_number . '</b> ' . Carbon::now()->format('Y-m-d H:i:s'),
            'user_name'     => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
        ]));

        dispatch(new \App\Jobs\Notes\StoreNote([
            'job_id'        => $newJob->id,
            'status_label'  => 'SR PO Created',
            'notes'         => $request->get('reason') . ' <b>' . $newJob->job_number . '</b> ' . Carbon::now()->format('Y-m-d H:i:s'),
            'user_name'     => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
        ]));

        //Creating User Action Record
        dispatch(new \App\Jobs\UserAction\TrackUserActions([
            'store_id' => $job->store->id,
            'job_id'   => $job->id,
            'user_id'  => $this->guard()->id(),
            'message'  => 'Cloned A PO',
            'action_date' => Carbon::now()->format('Y-m-d H:i:s')
        ]));

        //Creating a Location Distance
        $location = \App\Models\GeoLocation::where('job_id', '=', $job->id)->first();
        $newLocation = $location->replicate();
        $newLocation->job_id = $newJob->id;
        $newLocation->save();

        //Creating Lead Safe
        $leadSafe = \App\Models\LeadSafe::where('job_id', '=', $job->id)->first();
        $newLeadSafe = $leadSafe->replicate();
        $newLeadSafe->job_id = $newJob->id;
        $newLeadSafe->save();

        $this->createPayrollEntry($newJob, true);

        return response()->json(['po' => $newJob]);
    }

    public function relatedJobs($jobId)
    {
        return response()->json($this->jobs->returnRelatedJobs($jobId));
    }

    public function manuallyAssoc($jobId, ManuallyAssocRequest $request)
    {
        $relatedJob = \App\Models\Job::where('job_number', $request->get('po_number'))
            ->where('store_id', $request->get('store_id'))
            ->select('id')
            ->first();

        if (!$relatedJob)
            return response()->json(['message' => 'Provided PO number was not found'], 404);

        if($relatedJob->id == $jobId)
            return response()->json(['message' => 'Provided PO is the same as original PO'], 404);

        \App\Models\JobRelated::create([
            'job_id' => $jobId,
            'related_job_id' => $relatedJob->id
        ]);

        return response()->json([]);
    }

    public function acknowledgePo($jobId)
    {
        $job = $this->jobs->find($jobId);

        $poList = [
            'poList' => [
                'po' => [
                    [
                        'poNumber' => $job->job_number,
                        'storeNumber' => $job->provider_store_number
                    ]
                ]
            ]
        ];

        $response = $this->homeDepotService->acknowledgeSpecificPo($poList);

        if ($response['status'] == 200)
            return response()->json([]);

        return response()->json(['message' => $response['message']], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/jobs/acknowledge-change",
     *     summary="Acknowledge PO in HD",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="po_cost",
     *         in="query",
     *         @OA\Schema(
     *             type="text"
     *         ),
     *         description="PO Original Cost",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *          name="po_number",
     *          in="query",
     *          @OA\Schema(
     *              type="text"
     *          ),
     *          description="Po Number",
     *          required=true,
     *      ),
     *     @OA\Parameter(
     *           name="store_number",
     *           in="query",
     *           @OA\Schema(
     *               type="text"
     *           ),
     *           description="Store Number",
     *           required=true,
     *       ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function acknowledgeChange(AcknowledgeChangeRequest $request)
    {
        $search = [
            'search' => [
                'storeNumber' => $request->get('store_number'),
                'poNumber'    => $request->get('po_number')
            ]
        ];

        $response =  $this->homeDepotService->poDetails($search);

        if($response['status'] != 200)
            return response()->json(['message' => $response['message']], 404);

        if($request->get('po_cost') != $response['data'][0]['poCostAmount']) {
            \App\Models\JobNote::create([
                'status_label' => 'Price Change Detected',
                'job_id' => $request->get('job_id'),
                'notes' => '<p>Price change detected on Acknowledge Change button. </p><p>Old price: ' . $request->get('po_cost') . '</p><p>New price: ' . $response['data'][0]['poCostAmount'] . '</p>',
                'user_name' => 'System'
            ]);
        }

        $data = [
            'storeNumber' => $request->get('store_number'),
            'poNumber'    => $request->get('po_number')
        ];

        $acknowledgeResponse = $this->homeDepotService->acknowledgeChange($data);

        if($acknowledgeResponse['status'] != 200)
            return response()->json(['message' =>  $acknowledgeResponse['message']], 404);

        return response()->json([]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/jobs/acknowledge-cancel",
     *     summary="Acknowledge Cancel PO in HD",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="po_cost",
     *         in="query",
     *         @OA\Schema(
     *             type="text"
     *         ),
     *         description="PO Original Cost",
     *         required=true,
     *     ),
     *     @OA\Parameter(
     *          name="po_number",
     *          in="query",
     *          @OA\Schema(
     *              type="text"
     *          ),
     *          description="Po Number",
     *          required=true,
     *      ),
     *     @OA\Parameter(
     *           name="store_number",
     *           in="query",
     *           @OA\Schema(
     *               type="text"
     *           ),
     *           description="Store Number",
     *           required=true,
     *       ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function acknowledgeCancel(AcknowledgeChangeRequest $request)
    {
        $data = [
            'storeNumber' => $request->get('store_number'),
            'poNumber'    => $request->get('po_number')
        ];

        $acknowledgeResponse = $this->homeDepotService->acknowledgeCancel($data);

        if($acknowledgeResponse['status'] != 200)
            return response()->json(['message' =>  $acknowledgeResponse['message']], 404);

        $job = \App\Models\Job::where('id', $request->get('job_id'))->first();

        if($job) {
            $job->job_next_action_date = Carbon::today()->format('Y-m-d H:i:s');
            $job->status_id = JobStatus::REQUEST_CANCEL;
            $job->save();
        }

        return response()->json([]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/jobs/{jobId}/send-hd-note",
     *     summary="Send Note To HD",
     *     tags={"Jobs"},
     *     @OA\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="X API key",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="note",
     *         in="query",
     *         @OA\Schema(
     *             type="text"
     *         ),
     *         description="Message",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="success",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Entity"
     *     )
     * )
     */
    public function sendHdNote($jobId, SendNoteToHD $request)
    {
        $job = $this->jobs->find($jobId);

        if ($job) {
            $response = $this->homeDepotService->poNotesCreate([
                'poNumber' => $job->job_number,
                'storeNumber' => $job->provider_store_number,
                'noteFor' => 'Store',
                'noteText' => $request->get('note')
            ]);

            if ($response['status'] == 200) {
                \App\Models\JobOpenNote::create([
                    'job_id' => $job->id,
                    'note_number' => 99,// We Can Hardcode this since we never gonna update or check something on jobs:pull-delete-notes
                    'note'  => $request->get('note'),
                    'note_for' => 'Store',
                    'is_open'  => 0,
                    'created_by'   => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
                ]);

                return response()->json([]);
            }
            else
                return response()->json(['message' => $response['message']], 404);
        }

        return response()->json(['message' => 'Job Not Found in Database'], 404);
    }

    public function poCompare(PoCompareRequest $request)
    {
        $totalHDCount = 0;
        $totalTBCount = 0;
        $stats = [];
        if(Carbon::parse($request->get('compare_date')) < Carbon::today()->subMonth())
            return response()->json(['message' => 'Compare Date must be no longer than 1 month in past'], 404);


        $store = \App\Models\Store::where('id', $request->get('store_id'))->first();

        if(!$store)
            return response()->json(['message' => 'Store not Found in the system'], 404);

        $search =  [
            'search' => [
                'createdDateFrom' => Carbon::parse($request->get('compare_date'))->format('Y-m-d'),
                'storeNumber'     => $store->store_number,
            ],
            'acknowledgePO' => 'N',
        ];

        $response = $this->homeDepotService->poWorkListDetails($search);

        if ($response['status'] == 200) {
            $totalHDCount = count($response['data']);

            foreach ($response['data'] as $item) {
                $job = \App\Models\Job::where('provider_store_number', $store->store_number)
                    ->where('job_number', $item['poNumber'])
                    ->first();

                $stats[] = [
                    'job_number' => $item['poNumber'],
                    'store_number' => $store->store_number,
                    'exists'     => (bool)$job,
                ];
            }
        }

        $totalTBCount = \App\Models\Job::where('provider_store_number', $store->store_number)
            ->where('created_at', '>=', Carbon::parse($request->get('compare_date'))->format('Y-m-d'))
            ->whereNotIn('status_id', [JobStatus::CLOSED_DONE, JobStatus::CANCELLED_REFUNDED, JobStatus::PENDING_CLOSE])
            ->count('id');

        return response()->json([
            'stats' => $stats,
            'total_hd_count' => $totalHDCount,
            'total_tb_count' => $totalTBCount
        ]);
    }

    public function requestUpdate($jobId)
    {
        $job = $this->jobs->find($jobId);

        if(!$job)
            return response()->json(['message' => 'PO not Found in database'], 404);

        $nextDate = $this->getNextWeekDay();

        $job->job_next_action_date  = $nextDate;
        $job->save();


        $response = $this->homeDepotService->poNotesCreate([
            'poNumber' => $job->job_number,
            'storeNumber' => $job->provider_store_number,
            'noteFor' => 'Store',
            'noteText' => 'Has Special Order Product arrived yet? Thanks!'
        ]);

        \App\Models\JobNote::create(
            [
                'job_id' => $job->id,
                'status_label' => 'Requested S/O Product Status',
                'notes' => 'Has Special Order Product arrived yet? Thanks!<br />[Next Action Date pushed to: '.$nextDate.']',
                'user_name' => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
            ]
        );

        if($response['status'] != 200)
            return  response()->json(['message' => 'Job Next Action date is updated but Note was not sent to store'], 404);

        return response()->json([]);
    }

    public function regenerateBlanks($jobId)
    {
        $job = $this->jobs->find($jobId);

        $is_service_po = str_contains($job->job_number, 'SR');

        if (!$is_service_po)
            $this->deleteBlankDocuments($jobId, $job->installer_id);


        if ($job->status_id == JobStatus::SCHEDULED) {
            dispatch(new \App\Jobs\Document\CreateBlank($jobId));
        }
    }
    //
    protected function processCloseHdJob($job)
    {
        $service_job = !is_numeric($job->job_number);
        $store_number = $job->store ? $job->store->store_number : null;

        if (!$store_number)
            return response()->json(['message' => 'Store Not exist for this job']);

        $data = [
            'poNumber' => $job->job_number,
            'storeNumber' => $store_number
        ];

        // Measure Job
        if (!$service_job && $job->job_type == 'M') {
            $data['acknowledgeMeasureDiagram'] = 'Y';
            $response = $this->homeDepotService->poClose('measure', $data);
            //Checking if job status already done in HomeDepot since it`s failed to close
            if ($response['status'] != 200)
                return response()->json(['message' => $response['message']], 404);

        } elseif ($job->job_type == 'I' && !$service_job) {
            $data['ackFaxLienWaiver'] = 'Y';
            $data['jobPermitNumber'] = is_null($job->permit_number) ? 'NA' : $job->permit_number;

            $response = $this->homeDepotService->poClose('install', $data);
            if ($response['status'] != 200)
                return response()->json(['message' => $response['message']], 404);
        }

        //Here We receive success status from HD and we can move in our procedures
        $this->processPayroll($job->installer_id, $job->id);

        //Do we need Archive this PO? .
        $job->status_id = JobStatus::PENDING_CLOSE;
        $job->completed_date = Carbon::now()->format('Y-m-d H:i:s');
        $job->save();
        //Creating Internal Note
        dispatch(new \App\Jobs\Notes\StoreNote([
            'job_id' => $job->id,
            'status_label' => JobStatus::CLOSED_DONE_LABEL,
            'notes' => 'This PO was Closed/Done on: ' . Carbon::now()->format('Y-m-d H:i:s'),
            'user_name' => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
        ]));
        //Storing User Action
        //Send Note To the Store

        if (!$service_job) {
            dispatch(new \App\Jobs\Notes\SendNoteToHomeDepot([
                'storeNumber' => $store_number,
                'poNumber' => $job->job_number,
                'noteFor' => 'Store',
                'noteText' => 'This PO was Closed Done on: ' . Carbon::now()->timezone(config('app.timezone_interface'))->format('Y-m-d H:i:s')
            ]));
        }

        dispatch(new \App\Jobs\UserAction\TrackUserActions([
            'store_id' => $job->store->id,
            'job_id' => $job->id,
            'user_id' => $this->guard()->id(),
            'message' => 'Job Closed/Done',
            'action_date' => Carbon::now()->format('Y-m-d H:i:s')
        ]));

        return response()->json([]);
    }

    protected function processPayroll($installerId, $jobId)
    {
        $now = Carbon::now()->setTimezone(config('app.timezone_interface'))->format('m/d/Y H:i:s');
        $note = '';
        $autoSubmits = \App\Models\InstallerPayout::where('installer_id', $installerId)->where('is_auto_submit', 1)->select('id')->first();
        $autoPayouts = \App\Models\InstallerPayout::where('installer_id', $installerId)->where('is_auto_paid', 1)->select('id')->first();

        if ($autoPayouts) {
            $lineItems = \App\Models\PayrollLineItem::where('job_id', $jobId)
                ->where('installer_id', $installerId)
                ->where('paid_out', 'No')
                ->get(); //We need this since notes...

            if ($lineItems->first()) {
                foreach ($lineItems as $lineItem) {
                    $lineItem->submitted_date = Carbon::today()->format('Y-m-d H:i:s');
                    $lineItem->paid_out = 'Yes';

                    $note .= '<p>Installer payroll is "Auto-Payout" or svc_type_code="M":</p>';
                    $note .= '<p>Marked as Paid: Yes, Date: ' . $now . ' (#' . $lineItem->id . ')</p>';
                }
            }
        }

        if ($autoSubmits) {
            $lineItems = \App\Models\PayrollLineItem::where('job_id', $jobId)
                ->where('installer_id', $installerId)
                ->where('is_submitted', 0)
                ->get();  //We need this since there is condition ...

            if ($lineItems->first()) {
                foreach ($lineItems as $lineItem) {
                    $lineItem->submitted_date = Carbon::today()->format('Y-m-d H:i:s');
                    $lineItem->is_submitted = 1;
                    if ($lineItem->fee_processed)
                        $lineItem->amount = '0.00';

                    $lineItem->save();

                    $note .= '<p>Installer payroll is "Auto-Submit" or svc_type_code="M":</p>';
                    $note .= '<p>Submitted: Yes, Date: ' . $now . '</p>';
                    if ($lineItem->fee_processed)
                        $note .= '<p>Processing Fee set to: $0. (#' . $lineItem->id . ')</p>';
                }
            }
        }

        dispatch(new \App\Jobs\Notes\StoreNote([
            'job_id' => $jobId,
            'status_label' => 'Auto-Submit/Payout',
            'notes' => $note,
            'user_name' => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
        ]));
    }

    protected function updateJobStatusOnHomeDepot($job, $statusId, $changeCode, $dateChanged = false)
    {
        $method = $job->job_type == 'I' ? 'install' : 'measure';

        $request['poNumber'] = $job->job_number;
        $request['storeNumber'] = $job->provider_store_number;
        $request['changeReasonCode'] = $changeCode;

        if ($job->job_type == 'M')
            $request['scheduleMeasureDate'] = Carbon::parse($job->job_next_action_date)->format('Y-m-d');
        else {
            $request['scheduleBeginDate'] = Carbon::parse($job->job_next_action_date)->setHour(Carbon::parse($job->schedule_start)->format('H'))->format('Y-m-d H:i:s');
            $request['scheduleEndDate'] = Carbon::parse($job->job_next_action_date)->setHour(Carbon::parse($job->schedule_end)->format('H'))->format('Y-m-d H:i:s');
        }

        if (strlen($changeCode) < 2)
            $request['changeReasonCode'] = '300';

        switch ($statusId) {
            case JobStatus::SCHEDULED:
                $response = $this->homeDepotService->poUpdate($method, $request);

                if(!$this->checkHdResponse($method, $response['message']))
                    return ['status' => 404];

                $note = 'PO scheduled for ' . Carbon::parse($job->job_next_action_date)->format('Y-m-d') .
                    '. VERIFY  FOLLOW UP DATE. If this is an install, please have material pulled and ready that morning for pickup. Thank You! (From Dragonfly & TBC)';

                if($job->is_schedule_note_send != 1 || $dateChanged)
                    $this->sendNoteToHDAndStoreInternally($job, $note);

                $job->is_schedule_note_send = 1;

                //TODO EMAIL will be sent here
                break;
            case JobStatus::LEFT_MESSAGE:
                $job->is_schedule_note_send = 0;
                $nextDate = $this->getNextWeekDay();

                $job->job_next_action_date = $nextDate;

                $note = 'Left message with customer on ' . Carbon::today()->timezone(config('app.timezone_interface'))->format('Y-m-d H:i:s');

                $this->sendNoteToHDAndStoreInternally($job, $note);
                break;
            case JobStatus::CALL_BACK:
                $job->is_schedule_note_send = 0;
                $response = $this->homeDepotService->poUpdate($method, [
                    'poNumber' => $job->job_number,
                    'storeNumber' => $job->provider_store_number,
                    'followUpDate' => Carbon::parse($job->job_next_action_date)->format('Y-m-d'),
                    'scheduleBeginDate' => Carbon::parse($job->job_next_action_date)->format('Y-m-d'),
                    'scheduleEndDate'   => Carbon::parse($job->job_next_action_date)->format('Y-m-d')
                ]);

                if(!$this->checkHdResponse($method, $response['message']))
                    return ['status' => 404];

                $note = 'Customer requested call back on ' . Carbon::parse($job->job_next_action_date)->format('Y-m-d');

                $this->sendNoteToHDAndStoreInternally($job, $note);

                break;
            case JobStatus::CANCELLED_REFUNDED:
                $job->is_schedule_note_send = 0;
                $job->job_next_action_date = Carbon::today()->format('Y-m-d');
                $job->is_archived = true;
                break;
            default:
                $job->is_schedule_note_send = 0;
        }

        $job->save();

        return ['status' => 200];
    }

    protected function createPayrollEntry($job, $zeroAmount = false)
    {
        $entry = \App\Models\Payroll::create([
            'provider_id' => $job->provider_id,
            'job_id' => $job->id,
            'store_number' => $job->store ? $job->store->store_number : null,
            'job_number' => $job->job_number,
            'original_amount' => $job->po_cost_amount,
            'retail_amount' => $job->po_cost_amount,
            'local_status' => JobStatus::where('id', $job->status_id)->first()->status,
            'remote_status' => $job->external_status
        ]);

        if ($entry) {
            $payout = \App\Models\InstallerPayout::where('installer_id', $job->installer_id)->first();
            if (!$payout) {
                $percentage = 0;
            } else {
                if ($job->job_type == 'M')
                    $percentage = $payout->details_payout;
                else
                    $percentage = $payout->job_payout;
            }

            \App\Models\PayrollLineItem::create([
                'payroll_id' => $entry->id,
                'job_id' => $job->id,
                'installer_id' => $job->installer_id,
                'type' => 'Labor',
                'amount' => $zeroAmount ? 0 : $job->po_cost_amount,
                'payout_percentage' => $percentage,
                'note' => 'Line item created by system: ' . date('m/d/Y h:i A', time()) . '.'
            ]);
        }
    }

    protected function deletePayrollEntry($jobId, $installerId)
    {
        $entry = \App\Models\Payroll::where('job_id', $jobId)->first();
        if ($entry) {
            \App\Models\PayrollLineItem::where('payroll_id', $entry->id)
                //->where('installer_id', $installerId)
                ->delete();
            $entry->delete();
        }
    }

    protected function deleteBlankDocuments($jobId, $installerId)
    {
        $localDocuments = \App\Models\JobLocalDocument::where('job_id', $jobId)->where('user_id', $installerId)->get();


        if($localDocuments) {
            $documentsCount = $localDocuments->count();

            foreach ($localDocuments as $localDocument) {
                $path = str_replace('/files/', env('DOCUMENTS_PATH'), $localDocument->url);
                $directoryPath = explode('/', $path);

                try {
                    $fileSystem = new Filesystem();

                    unlink($path);

                    for($j = 1; $j <= 2; $j++) {
                        if($j == 1)
                            unset($directoryPath[count($directoryPath) - $j]);
                        else {
                            unset($directoryPath[count($directoryPath) - 1]);
                        }

                        $directory = implode('/', $directoryPath);

                        if ($fileSystem->exists($directory)) {
                            $files = $fileSystem->files($directory);
                            $directories = $fileSystem->directories($directory);
                            if (empty($files) && empty($directories)) {
                                $fileSystem->deleteDirectory($directory);
                            }
                        }
                    }

                }catch (\Exception $e) {
                    Log::debug('tried to remove Blank Documents for Job ID'  .  $jobId  . ', Error' . $e->getMessage());
                }

                $localDocument->delete();
            }
        }
    }

    protected function updateCalendarEntry($job, $installerId, $nextDate, $request)
    {
        $event = \App\Models\Event::where('job_id', $job->id)->first();

        if(!$event && $request->get('status_id') == JobStatus::SCHEDULED)
            $this->createCalendarEntry($job, $request->get('schedule_start'), $request->get('schedule_end'));
        else if($event && $request->get('status_id') != JobStatus::SCHEDULED)
            $event->delete();
        else if($event && $request->get('status_id') == JobStatus::SCHEDULED) {
            if ($event->target_user_id != $installerId)
                $event->target_user_id = $installerId;

            $event->event_date = Carbon::parse($nextDate)->format('Y-m-d');
            $event->event_time_start = $request->get('schedule_start') ? $request->get('schedule_start') : 0;
            $event->event_time_end = $request->get('schedule_end') ? $request->get('schedule_end') : 0;
            $event->save();
        }
    }

    protected function createCalendarEntry($job, $startTime, $endTime)
    {
        $customer = $job->customer;

        \App\Models\Event::create([
            'job_id'           => $job->id,
            'store_id'         => $job->store? $job->store->id : null,
            'created_user_id'  => 1,
            'target_user_id'   => $job->installer_id,
            'customer_name'    => $customer->first_name . ' ' . $customer->last_name,
            'customer_address' => $customer->address_line_1 . ' ' . $customer->address_line_2 . ' ' .$customer->city . ',' . $customer->state . ' ' . $customer->zip_code,
            'title'            => $job->job_type . ' ' . $customer->first_name . ' ' . $customer->last_name . '(' . $job->provider_store_number . '-' . $job->job_number . ')',
            'event_date'       => Carbon::parse($job->job_next_action_date)->format('Y-m-d'),
            'event_time_start' => $startTime,
            'event_time_end'   => $endTime,
            'description'      => 'THD NOTES: ' . $job->special_instructions,
            'event_type'       => 'J',
            'class_name'       => 'jclass'
        ]);
    }

    protected function updateJobDetails($job)
    {
        $oldPrice = $job->po_cost_amount;

        $response = $this->homeDepotService->poDetails([
            'search' => [
                'poNumber' => $job->job_number,
                'storeNumber' => $job->provider_store_number,
            ]
        ]);

        if ($response['status'] != 200)
            return response()->json(['message' => 'PO not found in Home Depot'], 404);


        //Price Change Detection
        if ($oldPrice != $response['data'][0]['poCostAmount'] && $oldPrice != 0) {
            \App\Models\JobNote::create([
                'status_label' => 'Price Change Detected',
                'job_id' => $job->id,
                'notes' => '<p>Price change detected on Bulk Update button.</p><p>Old price: ' . $oldPrice . '</p><p>New price: ' . $response['data'][0]['poCostAmount'] . '</p>',
                'user_name' => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
            ]);
        }

        $newData = $response['data'][0];

        $jOriginal = $job->getOriginal();
        //Updating Job Variables
        $job->po_cost_amount = $newData['poCostAmount'];
        $job->retail_amount = $newData['poRetailAmount'];
        $job->product_type = $newData['shortSkuDesc'];
        $job->external_status = $newData['poStatusDesc'];
        $job->job_type = $newData['svcTypeCode'];
        $job->special_instructions = $newData['specialInstructionText'];

        if($newData['poStatusDesc'] == 'Done')
            $job->status_id = JobStatus::CLOSED_DONE;

        $job->save();

        //Update Job Options
        $additionalOptions = \App\Models\JobAdditionalOption::where('job_id', $job->id)->first();
        if ($additionalOptions) {
            $aOriginal = $additionalOptions->getOriginal();
            $additionalOptions->update([
                'install' => array_key_exists('installInfo', $newData) ? json_encode($newData['installInfo']) : null,
                'custom' => array_key_exists('customInstalls', $newData) ? json_encode($newData['customInstalls']) : null,
                'optional' => array_key_exists('optionalInstalls', $newData) ? json_encode($newData['optionalInstalls']) : null
            ]);

            $old = array_merge($aOriginal, $jOriginal);
            $new = array_merge($job->getChanges(), $additionalOptions->getChanges());
        }

        //Update Customer/Customer Phones and Emails
        $customer = \App\Models\Customer::where('id', $job->customer_id)->first();
        $cOriginal = $customer->getOriginal();
        $customer->update([
            'first_name' => !empty($newData['customerInfo']) ? $newData['customerInfo']['firstName'] : null,
            'last_name' => !empty($newData['customerInfo']) ? $newData['customerInfo']['lastName'] : null,
            'store_number' => $newData['storeNumber'],
            'po_number' => $newData['poNumber']
        ]);

        $old = array_merge($old ?? [], $cOriginal);
        $new = array_merge($new ?? [], $customer->getChanges());

        $this->notesService->saveJobChangesToNotes($new, $old, $this->guard()->user(), 'Pulled Job From Home Depot', $job->id);

        foreach ($newData['customerInfo']['phones']['phone'] as $phone) {

            if(!str_contains($phone['phoneNumber'], '(')) {
                $extension = substr($phone['phoneNumber'], 0, 3);
                $code = substr($phone['phoneNumber'], 3, 3);
                $last = substr($phone['phoneNumber'], 6);

                $extPhone = '(' . $extension . ') ' . $code . '-' . $last;
            }else {
                $extPhone = $phone['phoneNumber'];
            }

            \App\Models\CustomerPhone::updateOrCreate(
                [
                    'customer_id' => $job->customer_id,
                    'phone' => $extPhone
                ],
                [
                    'phone' => $extPhone,
                    'phone_type' => $phone['phoneType'],
                ]
            );
        }

        foreach ($newData['customerInfo']['emails']['email'] as $email) {
            \App\Models\CustomerEmail::updateOrCreate(
                [
                    'customer_id' => $job->customer_id,
                    'email' => $email['emailAddress'],
                ],
                [
                    'email' => $email['emailAddress'],
                    'primary' => $email['primaryEmailInd'] == 'YES' ? 1 : 0
                ]
            );
        }

        if (!empty($newData['specialOrderMerchandises']['specialOrderMerchandise'])) {
            foreach ($newData['specialOrderMerchandises']['specialOrderMerchandise'] as $merch) {
                $this->createJobProduct($job, $merch);
            }
        }

        if (!empty($newData['regularMerchandises']['regularMerchandise'])) {
            foreach ($newData['regularMerchandises']['regularMerchandise'] as $merch) {
                $this->createJobProduct($job, $merch);
            }
        }

        if(isset($newData['otherServices']) && !empty($newData['otherServices']['otherService'])) {
            foreach ($newData['otherServices']['otherService'] as $otherPo) {
                $this->createRelatedPo($otherPo, $job->id, $job->provider_store_number, $job->job_number);
            }
        }
    }

    protected function updateLeadSafeData($job)
    {
        $search = [
            'search' => [
                'storeNumber' => $job->provider_store_number,
                'poNumber' => $job->job_number
            ]
        ];

        $response = $this->homeDepotService->epaSearchList($search);

        if ($response['status'] != 200)
            return response()->json(['message' => 'PO not found in Home Depot'], 404);

        \App\Models\LeadSafe::updateOrCreate(
            [
                'job_id' => $job->id,
            ],
            [
                'api_build_year' => '0',
                'hd_job_id' => $response['data'][0]['jobId'],
                'job_status' => $response['data'][0]['jobStatus'],
                'job_type' => $response['data'][0]['jobType'],
                'message' => $response['data'][0]['message'],
                'override_reason' => $response['data'][0]['ovrdeRsn'],
                'override_status' => $response['data'][0]['ovrdeStatus'],
                'override_reason_code'  => $response['data'][0]['ovrdeReqCd'],
                'calculated_build_year' => $response['data'][0]['calcYrBuilt'],
                'confirmed_build_year' => $response['data'][0]['confYrBuilt'],
                'lswp_required' => $response['data'][0]['lswpRequired'],
                'lswp_followed' => $response['data'][0]['lswpFollowed'],
                'lead_test_required' => $response['data'][0]['leadTestReq'],
                'lead_test_status' => $response['data'][0]['leadTestStatus'],
                'cust_order_id' => $response['data'][0]['custOrdId'],
                'pay_status' => $response['data'][0]['payStatus'],
                'documents_list' => $response['data'][0]['docs'],
            ]
        );

        if(!empty($response['data'][0]['docs'])){
            foreach ($response['data'][0]['docs'] as $doc) {
                JobLeadSafeDocument::updateOrCreate(
                    [
                        'job_id' => $job->id,
                        'document_id' => $doc['docCd']
                    ],
                    [
                        'document_status' => $doc['docStatus']
                    ]
                );
            }
        }
    }

    protected function createJobProduct($job, $merch)
    {
        try {
            \App\Models\JobProduct::updateOrCreate(
                [
                    'job_id' => $job->id,
                    'item_number' => $merch['skuNumber']
                ],
                [
                    'description' => $merch['skuDesc'],
                    'short_merch_desc' => $merch['merchandisingDesc'],
                    'type' => $merch['suoiDesc'],
                    'qty' => $merch['orderQuantity'],
                    'unit_cost' => $merch['orderRetailAmount'],
                    'total_cost' => $merch['orderCostAmount'],
                    'sku' => $merch['skuNumber'],
                    'item_number' => $merch['skuNumber'],
                    'eta' => ($merch['expectedArrivalDate'] != '' && Carbon::createFromFormat('Y-m-d', $merch['expectedArrivalDate'])) ? $merch['expectedArrivalDate'] : null
                ]
            );
        } catch (\Exception $e) {
            //Log here ? .
        }
    }

    protected function createRelatedPo($otherPo, $localPoId, $storeNumber, $localPoNumber)
    {
        if($otherPo['poBelongTo'] == 'SAME_MVENDOR' && $otherPo['poNumber'] != $localPoNumber) {
            $relatePo = \App\Models\Job::where('provider_store_number', $storeNumber)
                ->where('job_number', $otherPo['poNumber'])
                ->select('id')
                ->first();

            if($relatePo) {
                \App\Models\JobRelated::create([
                    'job_id' => $localPoId,
                    'related_job_id' => $relatePo->id
                ]);
            }
        }
    }

    protected function defineServicePoNumber($jobNumber, $storeNumber): string
    {
        $job = \App\Models\Job::where('job_number', 'LIKE', '%SR' . $jobNumber . '%')
            ->where('provider_store_number', $storeNumber)
            ->latest()
            ->first();
        if (!$job)
            return 'SR' . $jobNumber . '-01';
        else {
            $jobn = explode('-', $job->job_number);
            $next = (int)$jobn[1] + 1;
            return 'SR' . $jobNumber . '-0' . $next;
        }
    }

    protected function sendNoteToHDAndStoreInternally($job, $note)
    {
        $this->homeDepotService->poNotesCreate([
            'poNumber' => $job->job_number,
            'storeNumber' => $job->provider_store_number,
            'noteFor' => 'Store',
            'noteText' => $note
        ]);

        \App\Models\JobOpenNote::create([
            'job_id' => $job->id,
            'note_number' => 99,
            'note'  => $note,
            'note_for' => 'Store',
            'is_open'  => 0,
            'created_by'   => $this->guard()->user()->first_name . ' ' . $this->guard()->user()->last_name
        ]);
    }

    protected function checkHdResponse($method, $message): bool
    {
        if ($method == 'install' && $message != 'Install PO updated successfully.')
            return false;

        if($method == 'measure' && $message != 'Measure PO updated successfully.')
           return false;

       return true;
    }

    protected function getNextWeekDay()
    {
        $nextDate = Carbon::now()->addDays(2);

        if($nextDate->isWeekDay())
            $nextDate = $nextDate->format('Y-m-d');
        else {
            if($nextDate->format('D') == 'Sat')
                $nextDate = $nextDate->addDays(2)->format('Y-m-d');
            else if($nextDate->format('D') == 'Sun')
                $nextDate = $nextDate->addDay()->format('Y-m-d');
        }
        return $nextDate;
    }

    protected function checkIfMeasureReadyToBeClosed($job): bool
    {
        if($job->completedDocuments()->where('type', 100)->where('is_processed', 1)->first())
            return true;

        return false;
    }

    protected function checkIfInstallIsReadyToBeClosed($job): bool
    {
        $completedDocument = $job->completedDocuments()->where('type', 650)->where('is_processed', 1)->first();

        if($job->flag_lead_safe != 1 && $completedDocument)
            return true;
        if($job->flag_lead_safe == 1 && $completedDocument && $job->leadSafe->is_lswp_complete == 1)
            return true;

        return false;
    }
    protected function guard()
    {
        return Auth::guard('jwt');
    }

}
