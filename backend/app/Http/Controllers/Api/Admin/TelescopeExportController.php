<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets an admin download Telescope's captured entries as a CSV straight from
 * the admin panel - no terminal, no cPanel file manager, no DB client. Reads
 * the telescope_entries table directly via the query builder rather than
 * Telescope's own classes, so this works even before/without
 * TelescopeServiceProvider being registered (see MONITORING.md) - the only
 * real requirement is that the table exists, i.e. its migration has run.
 *
 * Deliberately NOT reusing Telescope's own /telescope UI or its passkey auth
 * (TelescopeAccessKey) - this piggybacks on the same JWT + admin-role
 * middleware every other /admin route already uses, which is simpler than
 * standing up a second auth mechanism just for exporting.
 */
class TelescopeExportController extends Controller
{
    /** Keeps a single export from trying to stream the entire table if someone forgets to narrow the filters. */
    private const MAX_ROWS = 20000;

    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:request,command,exception,job,log,mail,notification,query,redis,cache,view,gate,batch,event,client_request,dump,schedule'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ROWS],
        ]);

        if (! Schema::hasTable('telescope_entries')) {
            abort(404, 'Telescope is not installed yet - see MONITORING.md for setup steps.');
        }

        $type = $data['type'] ?? null;
        $hours = $data['hours'] ?? 48; // matches the telescope:prune --hours=48 schedule, so this covers "everything currently retained" by default
        $limit = $data['limit'] ?? self::MAX_ROWS;

        $query = DB::table('telescope_entries')
            ->where('created_at', '>=', now()->subHours($hours))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->limit($limit);

        $filename = 'telescope-export-'.($type ?? 'all').'-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['uuid', 'batch_id', 'type', 'family_hash', 'created_at', 'content']);

            // cursor() rather than get()/chunk() - streams rows one at a time
            // straight from the DB connection instead of loading up to 20k
            // rows into memory before the first byte goes out.
            foreach ($query->cursor() as $entry) {
                fputcsv($out, [
                    $entry->uuid,
                    $entry->batch_id,
                    $entry->type,
                    $entry->family_hash,
                    $entry->created_at,
                    $entry->content, // raw JSON as captured by Telescope - kept intact rather than flattened, since shape varies a lot by type (request vs query vs exception, etc.)
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
