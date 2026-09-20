<?php

namespace App\Console\Commands;

use App\Models\GardenBedPlant;
use App\Models\GardenReminder as ReminderRecord;
use App\Models\Plant;
use App\Models\User;
use App\Notifications\GardenReminder;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Turns the plant library's calendar into timely nudges.
 *
 * Two kinds. "Sow now" comes from a saved plant whose planting months include
 * this one. "Ready to harvest" comes from something actually planted in a bed,
 * once days_to_maturity has elapsed.
 *
 * Runs daily and is safe to run repeatedly: every send is guarded by a unique
 * row, so a second run the same day sends nothing.
 */
class SendGardenReminders extends Command
{
    protected $signature = 'garden:reminders {--now= : Treat this ISO date as today, for testing}';

    protected $description = 'Notify gardeners about sowing windows and crops coming ready';

    public function handle(): int
    {
        $today = $this->option('now') ? Carbon::parse($this->option('now')) : Carbon::today();

        $sown = $this->remindToSow($today);
        $harvest = $this->remindToHarvest($today);

        $this->info("Sent {$sown} sowing and {$harvest} harvest reminders.");

        return self::SUCCESS;
    }

    private function remindToSow(Carbon $today): int
    {
        $month = (int) $today->month;
        $period = $today->format('Y-m');
        $sent = 0;

        // Saved plants are the ones someone has actually shown interest in,
        // so they are the only ones worth nudging about.
        User::query()
            ->whereHas('savedPlants')
            ->with('savedPlants')
            ->chunkById(100, function ($users) use ($month, $period, &$sent) {
                foreach ($users as $user) {
                    foreach ($user->savedPlants as $plant) {
                        if (! $plant->plantableInMonth($month)) {
                            continue;
                        }

                        if ($this->claim($user->id, ReminderRecord::SOW, $plant->id, $period)) {
                            $user->notify(new GardenReminder(
                                ReminderRecord::SOW,
                                "Time to sow {$plant->name}",
                                "This month is in {$plant->name}'s sowing window.",
                                "/plants/{$plant->id}",
                            ));
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    private function remindToHarvest(Carbon $today): int
    {
        $sent = 0;

        GardenBedPlant::query()
            ->whereNotNull('planted_at')
            ->with(['plant', 'gardenBed.user'])
            ->chunkById(200, function ($entries) use ($today, &$sent) {
                foreach ($entries as $entry) {
                    $plant = $entry->plant;
                    $user = $entry->gardenBed?->user;
                    $days = $plant?->days_to_maturity;

                    if ($user === null || $days === null) {
                        continue;
                    }

                    $due = Carbon::parse($entry->planted_at)->addDays($days);

                    if ($due->greaterThan($today)) {
                        continue;
                    }

                    // "once": a crop only comes ready the one time.
                    if ($this->claim($user->id, ReminderRecord::HARVEST, $entry->id, 'once')) {
                        $user->notify(new GardenReminder(
                            ReminderRecord::HARVEST,
                            "{$plant->name} should be ready",
                            "It has been {$days} days since you planted it.",
                            "/garden/{$entry->garden_bed_id}",
                        ));
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Claim the right to send one reminder. The unique index does the work:
     * if the row already exists the insert fails and we know it has gone out,
     * which is race-safe in a way that "select then insert" is not.
     */
    private function claim(int $userId, string $kind, int $subjectId, string $period): bool
    {
        try {
            ReminderRecord::create([
                'user_id' => $userId,
                'kind' => $kind,
                'subject_id' => $subjectId,
                'period' => $period,
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
