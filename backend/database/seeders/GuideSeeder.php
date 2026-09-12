<?php

namespace Database\Seeders;

use App\Models\Guide;
use App\Models\Plant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GuideSeeder extends Seeder
{
    private const GUIDES = [
        [
            'title' => 'Starting Your First Vegetable Garden',
            'category' => 'getting_started',
            'read_minutes' => 5,
            'excerpt' => 'The five decisions that matter before you buy a single seed.',
            'body' => "Most new vegetable gardens fail for the same handful of reasons, and none of them are lack of green fingers.\n\nFirst, pick a spot with at least six hours of direct sun. Most vegetables that fruit (tomatoes, beans, courgettes) need it, and no amount of good soil makes up for a shady corner. Second, start smaller than you want to. A 2x2m bed, fully looked after, beats a 6x6m plot that gets away from you by July.\n\nThird, work out your soil before you plant anything. See our soil guide below. Fourth, group plants by water needs, not just by what looks nice together; it makes watering far simpler. Fifth, and most skipped: check what actually grows in your hardiness zone and season before you fall in love with something that needs a climate you don't have.\n\nBeyond that, the only real skill is showing up regularly. Fifteen minutes most days beats three hours once a fortnight.",
        ],
        [
            'title' => 'Understanding Your Hardiness Zone',
            'category' => 'getting_started',
            'read_minutes' => 4,
            'excerpt' => "What that 'zone 8' number on a seed packet is actually telling you.",
            'body' => "A hardiness zone is a rough measure of how cold your area gets in an average winter. It's a proxy for one thing: whether a perennial plant will survive being left outside all year where you live.\n\nIf a plant is rated to zone 7 and you're in zone 8, it should overwinter fine. If you're in zone 5 and it's rated to zone 7, it'll likely die back in a hard frost unless you bring it inside or treat it as an annual.\n\nIt matters less for plants you grow as annuals anyway. Most vegetables are replanted every year regardless of zone, so the more useful number for those is the planting month, not the zone. Where zone really earns its keep is for anything you're hoping will come back next year: fruit trees, perennial herbs like rosemary, shrubs.\n\nOne caveat worth knowing: zone only reflects average minimum temperature, not rainfall, humidity, or how exposed your specific garden is. A sheltered courtyard can comfortably grow things a zone above its official rating; an exposed hilltop plot often can't.",
        ],
        [
            'title' => 'Reading Your Soil: A Beginner\'s Guide to Soil Types',
            'category' => 'soil_and_feeding',
            'read_minutes' => 5,
            'excerpt' => 'A two-minute jar test tells you more than any amount of guessing.',
            'body' => "Take a handful of damp soil and squeeze it. If it falls apart immediately, you likely have sandy soil. It drains fast and won't hold nutrients well, but it warms up quickly in spring and rarely waterlogs. If it holds a tight, shiny ball that barely breaks apart, that's clay, nutrient-rich but slow to drain and slow to warm, which is why clay soil seedlings often sulk in cold, wet spring ground. Somewhere between the two, crumbly and easy to work, is loam, what most vegetables actually want.\n\nFor a more precise read, the classic method is a jar test: fill a clear jar a third full of soil, top up with water, shake hard, and leave it overnight. It settles into layers: sand at the bottom, silt in the middle, clay on top, and the relative thickness of each layer tells you your rough soil composition.\n\nYou can't change your fundamental soil type overnight, but you can improve any of them the same way: dig in well-rotted compost every season. It helps sandy soil hold water and nutrients, and helps clay soil drain and warm up faster.",
        ],
        [
            'title' => 'When and How to Feed Your Plants',
            'category' => 'soil_and_feeding',
            'read_minutes' => 4,
            'excerpt' => "More plants are harmed by overfeeding than by underfeeding. Here's how to get it right.",
            'body' => "Most garden soil, if it's had compost added in the last year or two, doesn't need much extra feeding for leafy vegetables and herbs. Where feeding earns its keep is with heavy fruiting crops: tomatoes, courgettes, peppers, which pull a lot out of the soil to produce fruit all summer.\n\nA rough rule: high-nitrogen feeds (or plain compost) favour leafy growth, which is what you want for lettuce, spinach, cabbage. High-potassium feeds (tomato feed is the classic example) favour flowering and fruiting, which is what you want once a fruiting plant has stopped just growing leaves and started flowering.\n\nThe most common mistake is feeding too early and too often. Heavy feeding a young plant before it's established pushes soft, leafy growth that's more attractive to pests and less resilient, and feeding a plant that already has plenty of nutrients just wastes feed and can scorch roots. Little and often, starting once a plant is flowering or fruiting, beats one big dose.",
        ],
        [
            'title' => 'Deep Watering vs Little and Often',
            'category' => 'watering',
            'read_minutes' => 4,
            'excerpt' => 'Why a quick daily sprinkle is often worse than watering properly twice a week.',
            'body' => "A light daily watering wets the top centimetre or two of soil and nothing more. Roots grow towards moisture, so shallow, frequent watering trains roots to stay near the surface, which makes the whole plant more vulnerable to drying out the moment you miss a day, and less resilient in hot weather.\n\nDeep watering less often does the opposite: soak the soil thoroughly so water reaches 15-20cm down, then let the surface dry out before watering again. Roots follow the water down, building a deeper, more drought-resilient root system.\n\nIn practice, that usually means two to three thorough waterings a week in normal weather rather than a splash every day, adjusted up in a heatwave and down in wet spells. Container plants are the exception: pots dry out fast and often do need daily attention in summer, since there's so little soil volume to hold reserve moisture.\n\nOne simple check: push a finger 3-4cm into the soil. If it's dry at that depth, water. If it's still damp, wait.",
        ],
        [
            'title' => 'Signs of Overwatering vs Underwatering',
            'category' => 'watering',
            'read_minutes' => 3,
            'excerpt' => 'They often look identical from a distance. Here\'s how to actually tell them apart.',
            'body' => "Both overwatered and underwatered plants droop and get yellow leaves, which is why it's the single most common misdiagnosis in gardening. The details tell them apart.\n\nUnderwatered plants droop but perk back up within an hour or two of watering. Leaves usually yellow and crisp from the edges inward, and the soil is visibly pulling away from the pot or cracking at the surface.\n\nOverwatered plants droop and stay drooped even when the soil is clearly wet: because waterlogged soil starves roots of oxygen, so a plant sitting in soggy soil can show the same wilting as one that's bone dry. Leaves tend to yellow from the base of the plant first, often feeling soft or mushy rather than crisp, and the soil smells sour rather than earthy.\n\nWhen in doubt, always check the soil with a finger before watering again: never water on a fixed schedule regardless of what the soil's actually doing.",
        ],
        [
            'title' => 'Natural Ways to Deal With Aphids',
            'category' => 'pest_control',
            'read_minutes' => 4,
            'excerpt' => "You don't need a spray for most aphid problems. Here's what actually works.",
            'body' => "A few aphids on a healthy plant usually isn't worth reacting to. They're a normal part of a garden's ecosystem, and ladybirds, hoverfly larvae and small birds all eat them. The problems start when a colony explodes with nothing keeping it in check.\n\nThe first response for a bad infestation, before reaching for anything else, is a strong jet of water from a hose or spray bottle aimed at the undersides of leaves where aphids cluster. It physically knocks most of them off and they rarely climb back on. Repeat every couple of days for a week.\n\nIf that's not enough, a simple diluted soap spray (a few drops of plain dish soap in a litre of water) suffocates soft-bodied aphids on contact without harming most beneficial insects once it's dried: test on one leaf first, as a few sensitive plants can react to it.\n\nPlanting things that attract aphid predators nearby: marigolds and other open, nectar-rich flowers: helps keep numbers down for the whole season rather than fighting the same battle on repeat.",
        ],
        [
            'title' => 'Keeping Slugs and Snails Off Your Seedlings',
            'category' => 'pest_control',
            'read_minutes' => 4,
            'excerpt' => 'Young seedlings are the vulnerable window: protect that, and the rest of the season gets easier.',
            'body' => "Slugs and snails do almost all their damage to young, soft seedlings. A mature, established plant can usually shrug off some leaf damage that would kill a two-week-old seedling overnight. That means the highest-value thing you can do is protect plants specifically in their first few weeks, not fight a running battle all season.\n\nA physical barrier works better than almost anything else: crushed eggshells, sharp grit, or a copper tape ring around a pot all rely on the same principle of making the surface unpleasant to cross. Raising seedlings in pots until they're a bit tougher before planting out, rather than direct-sowing into open ground, sidesteps the worst of the risk entirely.\n\nGoing out at dusk with a torch, when slugs are most active, and physically removing them is unglamorous but genuinely effective if you keep at it for a week or two during the vulnerable period. Encouraging natural predators: birds, frogs, hedgehogs: by leaving a bit of cover in the garden pays off over a whole season far more than any single treatment.",
        ],
        [
            'title' => 'Autumn Garden Jobs You Shouldn\'t Skip',
            'category' => 'seasonal',
            'read_minutes' => 4,
            'excerpt' => 'The unglamorous autumn tasks that make spring dramatically easier.',
            'body' => "It's tempting to consider the garden done for the year once the last tomatoes are in, but a handful of autumn jobs make a real difference to how the garden performs next spring.\n\nClear spent summer crops promptly rather than leaving them to rot down where they stand: old plant material is one of the main places pests and diseases overwinter. Add a thick layer of compost or well-rotted manure to empty beds now; worms will have worked it into the soil structure by spring, saving you the job later.\n\nIt's also the right time to plant garlic and overwintering onion sets, which need a cold spell to develop properly, and to lift and store any tender bulbs that won't survive a hard frost.\n\nFinally, give tools a proper clean before they're put away for winter: caked-on soil holds moisture against metal and is the single biggest cause of rust and pitted blades by spring.",
        ],
        [
            'title' => 'Protecting Tender Plants From Frost',
            'category' => 'seasonal',
            'read_minutes' => 3,
            'excerpt' => 'A frost warning doesn\'t have to mean losing a season\'s growth overnight.',
            'body' => "A single unexpected frost can undo months of growth on tender plants, but most frost damage is preventable with about ten minutes of warning.\n\nHorticultural fleece thrown loosely over plants overnight traps a few degrees of warmth near the ground and is usually enough for a light frost. The key word is loosely, since fleece pulled tight against leaves offers almost no protection where it touches. For container plants, simply moving pots against a house wall overnight uses the building's residual warmth and can be the difference between damage and none.\n\nWatering the soil (not the leaves) before a cold night also helps, since damp soil holds heat better than dry soil and releases it slowly overnight. What doesn't help is watering leaves directly before a frost. Wet foliage freezes faster than dry.\n\nIf frost does catch a plant out, resist the urge to prune away the damage immediately. Blackened, frost-damaged growth actually protects what's underneath from a second frost; it's safer to leave it until the real risk of frost has passed and prune once, properly, in spring.",
        ],
        [
            'title' => 'Five Tools Every New Gardener Actually Needs',
            'category' => 'tools',
            'read_minutes' => 3,
            'excerpt' => 'Skip the twelve-piece set. This is genuinely all you need to start.',
            'body' => "A hand trowel is the single most-used tool in most gardens: for planting, transplanting, and general digging in beds and containers, buy one decent stainless steel trowel rather than a cheap one that'll bend on the first stony patch of ground.\n\nA pair of bypass secateurs (the scissor-action kind, not anvil-action) handles most pruning, deadheading, and harvesting cleanly without crushing stems. A hand fork for weeding between close-planted rows saves your back compared to a full-size fork.\n\nA watering can with a rose (the perforated head) attachment lets you switch between a gentle shower for seedlings and a stronger direct stream for established plants. And a kneeling pad, however unglamorous, is the tool most new gardeners regret not buying sooner. An afternoon of weeding on bare knees is a genuinely good way to put yourself off gardening entirely.\n\nEverything beyond these five is genuinely optional until a specific job calls for it.",
        ],
        [
            'title' => 'Keeping Your Tools Sharp and Rust-Free',
            'category' => 'tools',
            'read_minutes' => 3,
            'excerpt' => 'Two minutes after each use adds years to a tool\'s life.',
            'body' => "A blunt pair of secateurs doesn't just make pruning harder. It crushes stems instead of cutting them cleanly, which leaves a ragged wound that's slower to heal and more open to disease. Sharpening a blade takes under a minute with a simple sharpening stone or file run along the bevelled edge, and doing it every few weeks in growing season keeps tools performing like new.\n\nRust is the other main tool-killer, and it's almost entirely preventable. Wipe soil off blades after every single use rather than letting it dry on: damp soil left in contact with metal overnight is exactly the condition rust needs. Once a season, a light coat of oil on metal parts (a cloth with a few drops of any general household oil works fine) keeps moisture off entirely.\n\nStoring tools hanging up rather than piled on a shed floor also matters more than people expect. It keeps edges from knocking against each other and stops tools sitting in any damp that collects at ground level.",
        ],
        [
            'title' => 'Composting 101: Turning Kitchen Scraps Into Garden Gold',
            'category' => 'composting',
            'read_minutes' => 5,
            'excerpt' => 'The one ratio that makes or breaks a compost heap.',
            'body' => "Composting is really just controlled rotting, and the single most important thing to get right is the balance between \"greens\" and \"browns\". Greens are nitrogen-rich, wet materials: vegetable peelings, grass clippings, tea bags. Browns are carbon-rich and dry: cardboard, dry leaves, straw, shredded paper.\n\nA heap that's all greens turns into a wet, smelly, slimy mess because there's nothing to let air through it. A heap that's all browns barely rots at all, since there's not enough nitrogen to feed the bacteria doing the actual decomposing. Aim for roughly equal parts by volume, layering them in as you add material rather than dumping a huge amount of one at once.\n\nTurning the heap every couple of weeks: literally forking it over so the outside goes to the middle: introduces oxygen and speeds everything up dramatically; an unturned heap can take a year or more, a regularly turned one can be ready in two to three months in warm weather.\n\nWhat not to add: meat, dairy, and cooked food attract pests and rot in a way that smells bad rather than composts well. Diseased plant material can also survive home composting temperatures and reinfect the garden when you spread the finished compost.",
        ],
        [
            'title' => 'Troubleshooting a Smelly or Slow Compost Bin',
            'category' => 'composting',
            'read_minutes' => 4,
            'excerpt' => 'Nearly every compost problem comes down to one of three fixes.',
            'body' => "A compost heap that smells bad: sharp, sour, almost sulphurous: is almost always too wet and too short of air. It's usually caused by too many greens (wet kitchen scraps, grass clippings) without enough dry, bulky brown material mixed through to let air reach the middle. The fix is to fork in a generous amount of dry material: shredded cardboard or dry leaves both work well, and turn the whole heap to break up any compacted, airless clumps.\n\nA heap that's just sitting there doing nothing is usually the opposite problem: too dry, too woody, or too cold. Compost needs to be about as damp as a wrung-out sponge: if it's crumbly and dry to the touch, water it as you turn it. If it's mostly woody prunings and cardboard with barely any green material, add a nitrogen-rich layer (grass clippings work fast) to kick bacterial activity back into gear.\n\nA heap that's attracting flies or rodents almost always has exposed food scraps at the surface. The fix is simply to bury new additions under a layer of existing compost or brown material rather than leaving them on top.",
        ],
        [
            'title' => 'Growing Great Tomatoes: A Beginner\'s Guide',
            'category' => 'getting_started',
            'read_minutes' => 5,
            'excerpt' => 'The plant most new gardeners start with, and the handful of things that separate a good crop from a disappointing one.',
            'plant' => 'Tomato',
            'body' => "Tomatoes are usually the first thing new gardeners try to grow, and for good reason: homegrown tomatoes genuinely taste different from anything in a shop. A few habits separate a heavy, healthy crop from a disappointing one.\n\nSupport them early. Tomato plants get top-heavy fast once fruit starts forming, and a cane or stake driven in at planting time (not added later, when it risks damaging roots) keeps the plant upright and fruit off the ground. Water consistently rather than letting the soil swing between bone-dry and soaked: irregular watering is the main cause of split fruit and blossom end rot, far more than any pest or disease.\n\nPinch out the small shoots that grow in the angle between the main stem and side branches (\"side shoots\") on cordon varieties: left alone, they turn one plant into a sprawling, unsupported bush that puts energy into leaves instead of fruit. Once the plant is flowering, switch to a potassium-rich tomato feed roughly weekly to support fruit development.\n\nAnd don't plant tomatoes near potatoes: both are susceptible to the same blight, and growing them close together makes an outbreak far more likely to spread between them.",
        ],
    ];

    public function run(): void
    {
        foreach (self::GUIDES as $data) {
            $plantId = isset($data['plant'])
                ? Plant::where('name', $data['plant'])->value('id')
                : null;

            Guide::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'body' => $data['body'],
                    'category' => $data['category'],
                    'plant_id' => $plantId,
                    'read_minutes' => $data['read_minutes'],
                    'published_at' => now()->subDays(random_int(1, 120)),
                ]
            );
        }
    }
}
