<?php

namespace App\Http\Controllers;

use App\Models\DtcCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DtcCodesController extends Controller
{
    // Digits and letters without 0, 1, o, l, i so printed codes can't be misread.
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 8;

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:64']]);

        $dtcCodes = DtcCode::query()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('code', strtoupper(trim($search))))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();
        $totalCodes = DtcCode::count();
        $usedCodes = DtcCode::where('is_use', true)->count();
        $availableCodes = $totalCodes - $usedCodes;

        return view('dtcCodes.index', compact('dtcCodes', 'totalCodes', 'usedCodes', 'availableCodes'));
    }

    public function create()
    {
        return view('dtcCodes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['counter' => ['required', 'integer', 'between:1,5000']]);

        $created = $this->generate((int) $data['counter']);

        return redirect()->route('dtc_codes.index')->with('status', "Generated {$created} DTC Codes.");
    }

    public function downloadCodes()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['DTC Code', 'Used', 'Created At']);

            DtcCode::chunkById(500, function ($codes) use ($out) {
                foreach ($codes as $code) {
                    fputcsv($out, [$code->code, (int) $code->is_use, $code->created_at?->format('Y-m-d H:i:s')]);
                }
            });

            fclose($out);
        }, 'dtc_codes_'.date('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Valid only while the code is unused. */
    public function isExist(string $code): JsonResponse
    {
        $record = DtcCode::code(strtoupper($code))->availableCodes()->first();

        return $this->codeResponse($record);
    }

    /** Valid whether or not the code was already used; the caller reads is_use. */
    public function isExistNew(string $code): JsonResponse
    {
        $record = DtcCode::code(strtoupper($code))->first();

        return $this->codeResponse($record, true);
    }

    private function codeResponse(?DtcCode $record, bool $withUsage = false): JsonResponse
    {
        if (! $record) {
            return response()->json(['success' => false, 'message' => 'DTC Code not found']);
        }

        $body = ['success' => true, 'data' => ['code' => $record->code, 'is_use' => $record->is_use]];
        if ($withUsage) {
            $body['is_use'] = $record->is_use;
        }

        return response()->json($body);
    }

    /** Generates $count distinct codes, retrying on collisions with existing ones. */
    private function generate(int $count): int
    {
        $created = 0;
        $attempts = 0;

        while ($created < $count && $attempts++ < 10) {
            $now = now();
            $batch = [];
            for ($i = $created; $i < $count; $i++) {
                $code = $this->randomCode();
                $batch[$code] = ['code' => $code, 'is_use' => false, 'created_at' => $now, 'updated_at' => $now];
            }

            foreach (array_chunk(array_values($batch), 500) as $rows) {
                $created += DtcCode::insertOrIgnore($rows);
            }
        }

        return $created;
    }

    private function randomCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
