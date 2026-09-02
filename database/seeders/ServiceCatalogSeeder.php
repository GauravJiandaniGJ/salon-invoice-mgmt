<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

/**
 * Service catalog transcribed from the salon's printed menu (PLAN.md §10).
 *
 * Idempotent: categories are matched by name, services by
 * (category, group_name, name). Existing rows are never overwritten so the
 * owner's UI edits (prices, active flags, ordering) survive re-seeding.
 *
 * Rows marked `// verify` were handwritten / ambiguous in the print.
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $index => $categoryData) {
            $category = ServiceCategory::firstOrCreate(
                ['name' => $categoryData['name']],
                ['audience' => $categoryData['audience'], 'sort_order' => $index + 1, 'is_active' => true],
            );

            foreach ($categoryData['services'] as $position => $row) {
                [$group, $name, $price] = $row;
                $extra = $row[3] ?? [];

                Service::firstOrCreate(
                    ['service_category_id' => $category->id, 'group_name' => $group, 'name' => $name],
                    [
                        'price' => $price,
                        'price_max' => $extra['price_max'] ?? null,
                        'description' => $extra['description'] ?? null,
                        'duration_minutes' => $extra['duration'] ?? null,
                        'sort_order' => $position + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array{name: string, audience: string, services: array<int, array{0: ?string, 1: string, 2: int|float, 3?: array<string, mixed>}>}>
     */
    protected function catalog(): array
    {
        return [
            ['name' => 'Cut & Style – Women', 'audience' => 'women', 'services' => [
                [null, 'Female Haircut', 500],
                [null, 'Fringe Cut', 150],
                [null, 'Girls Haircut (upto 12 years)', 350],
                ['Hair Wash', 'Upto Shoulder', 200],
                ['Hair Wash', 'Below Shoulder', 300],
                ['Hair Wash', 'Upto Waist', 400],
                ['Premium Wash (Sulfate Free)', 'Upto Shoulder', 240], // derived: menu says "20% extra"
                ['Premium Wash (Sulfate Free)', 'Below Shoulder', 360], // derived
                ['Premium Wash (Sulfate Free)', 'Upto Waist', 480], // derived
                ['Blow Dry', 'Upto Shoulder', 200],
                ['Blow Dry', 'Below Shoulder', 350],
                ['Blow Dry', 'Upto Waist', 450],
                ['Blow Dry with Wash', 'Upto Shoulder', 400],
                ['Blow Dry with Wash', 'Below Shoulder', 500],
                ['Blow Dry with Wash', 'Upto Waist', 600],
            ]],

            ['name' => 'Color Services – Women', 'audience' => 'women', 'services' => [
                ['Basic Touch Up (2 inches)', 'Basic', 1150],
                ['Basic Touch Up (2 inches)', 'Ammonia Free', 1400],
                ['Basic Touch Up (4 inches)', 'Basic', 1400],
                ['Basic Touch Up (4 inches)', 'Ammonia Free', 1800],
                ['Global Color / Highlights – Upto Neck', 'Basic', 1650],
                ['Global Color / Highlights – Upto Neck', 'Ammonia Free', 2000],
                ['Global Color / Highlights – Upto Shoulder', 'Basic', 2500],
                ['Global Color / Highlights – Upto Shoulder', 'Ammonia Free', 3000],
                ['Global Color / Highlights – Below Shoulder', 'Basic', 3000],
                ['Global Color / Highlights – Below Shoulder', 'Ammonia Free', 3500],
                ['Global Color / Highlights – Upto Waist', 'Basic', 3500],
                ['Global Color / Highlights – Upto Waist', 'Ammonia Free', 4000],
                ['Balayage / Ombre – Upto Neck', 'Basic', 3000],
                ['Balayage / Ombre – Upto Neck', 'Ammonia Free', 3500],
                ['Balayage / Ombre – Upto Shoulder', 'Basic', 4000],
                ['Balayage / Ombre – Upto Shoulder', 'Ammonia Free', 4500],
                ['Balayage / Ombre – Below Shoulder', 'Basic', 5000],
                ['Balayage / Ombre – Below Shoulder', 'Ammonia Free', 6500],
                ['Balayage / Ombre – Upto Waist', 'Basic', 7000],
                ['Balayage / Ombre – Upto Waist', 'Ammonia Free', 7500],
                ['Color Streak', 'Basic', 300],
                ['Color Streak', 'Ammonia Free', 350],
                ['Pre Lightening', 'Basic', 2000],
                ['Pre Lightening', 'Ammonia Free', 2000],
            ]],

            ['name' => 'Hair Treatments – Women', 'audience' => 'women', 'services' => [
                [null, 'Dandruff / Dry Scalp Treatment', 1250],
                ['Olaplex', 'Upto Shoulder', 2500], // menu spells "Olapex"
                ['Olaplex', 'Below Shoulder', 2800],
                ['Olaplex', 'Upto Waist', 3000],
                ['Olaplex', 'Upto Shoulder (DD)', 3000],
                ['Olaplex', 'Below Shoulder (DD)', 3300],
                ['Olaplex', 'Upto Waist (DD)', 3500],
                ['Dryness Control Treatment', 'Upto Shoulder', 1500],
                ['Dryness Control Treatment', 'Below Shoulder', 1750],
                ['Dryness Control Treatment', 'Upto Waist', 2250],
                ['Color Protect Treatment', 'Upto Shoulder', 800],
                ['Color Protect Treatment', 'Below Shoulder', 1000],
                ['Color Protect Treatment', 'Upto Waist', 1250],
            ]],

            ['name' => 'Texture Services – Women', 'audience' => 'women', 'services' => [
                ['Keratin', 'Upto Neck', 2500],
                ['Keratin', 'Upto Shoulder', 3500],
                ['Keratin', 'Below Shoulder', 4500],
                ['Keratin', 'Upto Waist', 5500],
                ['QOD', 'Upto Neck', 3000],
                ['QOD', 'Upto Shoulder', 4000],
                ['QOD', 'Below Shoulder', 5000],
                ['QOD', 'Upto Waist', 6250],
                ['Botox', 'Upto Neck', 4000], // verify (handwritten)
                ['Botox', 'Upto Shoulder', 5000], // verify (handwritten)
                ['Botox', 'Below Shoulder', 6000], // verify (handwritten)
                ['Botox', 'Upto Waist', 7000], // verify (handwritten)
                ['Oleo Shape Shine (Straight)', 'Upto Neck', 2500],
                ['Oleo Shape Shine (Straight)', 'Upto Shoulder', 3000],
                ['Oleo Shape Shine (Straight)', 'Below Shoulder', 4500],
                ['Oleo Shape Shine (Straight)', 'Upto Waist', 5500],
                ['Oleo Shape Shine (Straight)', 'Regrowth (<4 inch) / Crown', 3000],
                ['Oleo Shape Shine (Bond)', 'Upto Neck', 3000],
                ['Oleo Shape Shine (Bond)', 'Upto Shoulder', 3500],
                ['Oleo Shape Shine (Bond)', 'Below Shoulder', 5500],
                ['Oleo Shape Shine (Bond)', 'Upto Waist', 6500],
                ['Oleo Shape Shine (Bond)', 'Regrowth (<4 inch) / Crown', 4000],
            ]],

            ['name' => 'Cut & Style – Men', 'audience' => 'men', 'services' => [
                [null, 'Haircut – Men', 225],
                [null, 'Haircut – Boys / Kids', 175],
                [null, 'Shave', 150],
                [null, 'Beard Crafting', 175],
                [null, 'Hair Wash with Hairstyle', 150],
                [null, 'Hair Style', 100],
            ]],

            ['name' => 'Color Services – Men', 'audience' => 'men', 'services' => [
                ['Hair Color – Men', 'Basic', 900],
                ['Hair Color – Men', 'Ammonia Free', 1100],
                ['Beard Color', 'Basic', 250],
                ['Beard Color', 'Ammonia Free', 350],
                ['Sidelock / Moustache', 'Basic', 150],
                ['Sidelock / Moustache', 'Ammonia Free', 200],
            ]],

            ['name' => 'Hair Treatments – Men', 'audience' => 'men', 'services' => [
                [null, 'Head Massage', 200], // verify (handwritten)
                [null, 'Head Massage with Wash', 250], // verify (handwritten)
                [null, 'Premium Head Massage', 300], // verify (handwritten)
                [null, 'Premium Head Massage with Wash', 350], // verify (handwritten)
                [null, 'Hair Spa', 650],
                ['Olaplex', 'Upto Neck – Men', 2000],
                ['Olaplex', 'Upto Neck – Men (DD)', 2500],
                [null, 'Dryness Control Treatment', 1250],
                [null, 'Color Protect Treatment', 900],
                [null, 'Dandruff / Dry Scalp Treatment', 800],
                [null, 'Scalp Detox', 400],
            ]],

            ['name' => 'Threading', 'audience' => 'all', 'services' => [
                [null, 'Eyebrows', 60],
                [null, 'Upper Lip', 30],
                [null, 'Lower Lip', 30],
                [null, 'Sidelocks', 60],
                [null, 'Cheeks', 80],
                [null, 'Chin', 40],
                [null, 'Forehead', 30],
                [null, 'Jawline', 80],
                [null, 'Full Face', 250],
                [null, 'Earlobes', 50],
                [null, 'Nose', 30],
            ]],

            ['name' => 'Peel Off Wax', 'audience' => 'all', 'services' => [
                [null, 'Eyebrows', 100],
                [null, 'Upper Lip', 50],
                [null, 'Lower Lip', 50],
                [null, 'Sidelocks', 100],
                [null, 'Cheeks', 150],
                [null, 'Chin', 70],
                [null, 'Forehead', 70],
                [null, 'Jawline', 100],
                [null, 'Full Face', 350],
                [null, 'Earlobes', 80],
                [null, 'Nose', 50],
            ]],

            ['name' => 'Regular Wax', 'audience' => 'women', 'services' => [
                [null, 'Underarms', 40],
                [null, 'Full Arms (with Underarms)', 200],
                [null, 'Half Arms', 150],
                [null, 'Full Legs', 450],
                [null, 'Half Legs', 300],
                [null, 'Full Front / Back', 500],
                [null, 'Half Front / Back', 300],
                [null, 'Stomach / Chest', 250],
                [null, 'Full Body (without Brazilian)', 2000],
            ]],

            // Printed values appear shifted one row below their labels — verify with owner.
            ['name' => 'Liposoluble Wax', 'audience' => 'all', 'services' => [
                ['Underarms', 'Women', 80], // verify
                ['Underarms', 'Men', 200], // verify
                ['Full Arms (with Underarms)', 'Women', 300], // verify
                ['Full Arms (with Underarms)', 'Men', 1000], // verify
                ['Half Arms', 'Women', 200], // verify
                ['Half Arms', 'Men', 750], // verify
                ['Full Legs', 'Women', 500], // verify
                ['Full Legs', 'Men', 1500], // verify
                ['Half Legs', 'Women', 400], // verify
                ['Half Legs', 'Men', 1000], // verify
                ['Full Front / Back', 'Women', 600], // verify
                ['Full Front / Back', 'Men', 1500], // verify
                ['Half Front / Back', 'Women', 400], // verify
                ['Half Front / Back', 'Men', 800], // verify
                ['Stomach / Chest', 'Women', 400], // verify
                ['Stomach / Chest', 'Men', 1000], // verify
                ['Behind', 'Women', 500], // verify
                ['Brazilian', 'Women', 1000], // verify
                ['Full Body (without Brazilian)', 'Women', 2500], // verify
                ['Full Body (with Brazilian)', 'Women', 3000], // verify
            ]],

            ['name' => 'O3+ D-Tan / Bleach', 'audience' => 'all', 'services' => [
                [null, 'Full Face & Neck', 400],
                [null, 'Neck', 150],
                [null, 'Underarms', 200],
                [null, 'Full Arms', 800],
                [null, 'Half Arms', 600], // verify (partly obscured)
                [null, 'Full Legs', 1000], // verify (partly obscured)
                [null, 'Half Legs', 800],
                [null, 'Full Front / Back', 1000],
                [null, 'Half Front / Back', 800],
                [null, 'Stomach', 500],
                [null, 'Full Body', 2250],
            ]],

            ['name' => 'Clean Ups & Masks', 'audience' => 'all', 'services' => [
                [null, 'Clean & Clear Clean Up (30 mins)', 700, ['description' => 'Cleansing, exfoliation & mask', 'duration' => 30]],
                [null, 'Quick Glow Mask', 500],
                [null, 'Youth Brightening / Rubber Mask', 1000],
                [null, 'Collagen Mask', 1500],
            ]],

            ['name' => 'Basic Facials', 'audience' => 'all', 'services' => [
                [null, 'Perfect Balance Facial', 1000, ['description' => 'All skin types; cleanse, blackhead removal, massage & pack', 'duration' => 40]],
                [null, 'Anti Tan Facial', 1400, ['description' => 'All skin types except sensitive & acne', 'duration' => 45]],
                [null, 'Oxyblast Facial', 1600, ['description' => 'All skin types; oxygen facial', 'duration' => 45]],
                [null, 'Glovite Facial', 1750, ['description' => 'Instant skin lightening; not for sensitive & acne skin', 'duration' => 45]],
                [null, 'Sensi Glow Facial', 1800, ['description' => 'For sensitive skin only', 'duration' => 60]],
                [null, 'Signature Facial', 2000, ['description' => 'All skin types', 'duration' => 60]],
            ]],

            ['name' => 'Premium Facials', 'audience' => 'all', 'services' => [
                [null, 'Episyl Facial (Pro Matte / Pro Hydra / Pro Merge)', 2500, ['description' => 'Oily, dry & combination skin']],
                [null, 'Power Brightening Facial', 2800, ['description' => 'Dull & lifeless skin, controls pigmentation', 'duration' => 70]],
                [null, 'Ultra Relaxing Facial', 3000, ['description' => 'Sensitive skin; oats & botanical actives']],
                [null, 'Anti Aging Facial', 3000, ['description' => 'Dry & mature skin', 'duration' => 70]],
                [null, 'Gensyl Facial (Walnut-Ginger / Papaya Marshmallow)', 3500, ['description' => 'Dull, dry skin; hyperpigmentation']],
                [null, 'Thalgo Facial', 3850, ['description' => 'Hydra marine; dull, dehydrated skin']],
                [null, 'Bride / Groom Facial', 4000, ['description' => 'Vitamin C', 'duration' => 90]],
                [null, 'Dermasyl Blanch Facial', 4500, ['description' => 'Fine lines, wrinkles, sensitivity & hyperpigmentation']],
            ]],

            ['name' => 'Hands & Feet Care', 'audience' => 'all', 'services' => [
                ['Basic', 'Cut & File (Hands / Feet)', 100],
                ['Basic', 'Cut, File & Paint (Hands / Feet)', 200],
                ['Basic', 'Regular Manicure', 550],
                ['Basic', 'Regular Pedicure', 600],
                ['Basic', 'Cocktail Manicure', 650],
                ['Basic', 'Cocktail Pedicure', 750],
                ['Advance', 'Relaxing Manicure', 800],
                ['Advance', 'Relaxing Pedicure', 1000],
                ['Advance', 'Crystal Spa Manicure', 1400],
                ['Advance', 'Crystal Spa Pedicure', 1800],
                ['Advance', 'Premium AVL Manicure', 2250],
                ['Advance', 'Premium AVL Pedicure', 2650],
                ['Advance', 'Reflexology', 600],
                ['Advance', 'Heel Peel Treatment', 2000],
            ]],

            ['name' => 'Nail Care', 'audience' => 'all', 'services' => [
                [null, 'Gel Nail Paint – Hands', 800],
                [null, 'Gel Nail Paint – Feet', 600],
                [null, 'Permanent Gel Extension', 2500],
                [null, 'Temporary Extension – Hands', 1000],
                [null, 'Nail Art per finger (with color)', 60],
                [null, 'Nail Art Adv. per finger (glitter / sticker)', 80],
                [null, 'Nail Art Adv. per finger (stones / accessories)', 100, ['price_max' => 500]],
                [null, 'Extension Removal – Gel', 800],
                [null, 'Extension Removal – Acrylic', 1000],
                [null, 'Gel Nail Paint Removal', 150],
            ]],

            ['name' => 'Makeup – Bride & Bridesmaids', 'audience' => 'women', 'services' => [
                [null, 'Engagement / Baby Shower / Mehendi / Sangeet Makeup (with Hairstyle & Draping)', 4000],
                [null, 'Wedding Makeup with Styling & Draping', 8000],
                [null, 'Reception Makeup with Styling & Draping', 6000],
                [null, 'Party Makeup', 1000],
                [null, 'Party Makeup with Styling & Draping', 1800],
                [null, 'Open Hairstyle', 800],
                [null, 'Bun / Updo', 1200],
                [null, 'Hair Accessories (as per actual)', 0], // price entered at billing
                [null, 'Venue Charges (as per distance / time / location)', 0], // price entered at billing
            ]],

            ['name' => 'Makeup – Men', 'audience' => 'men', 'services' => [
                [null, 'Groom Makeup (Wedding / Reception)', 1000],
                [null, 'Party Makeup for Men', 500],
            ]],
        ];
    }
}
