<?php
declare(strict_types=1);

/**
 * Construction calculators, metal calculators, unit converters and the area
 * calculator of the Floor Plan app, ported from the Flutter/Dart screens in
 * lib/screens/calculator/. Formulas and constants match the Dart code.
 *
 * Public API (used by WebApp and views/app/calculators/*):
 *   Calculators::hub()                 tabs + tiles for /app/calculators
 *   Calculators::find($id)             one calculator definition (or null)
 *   Calculators::compute($calc, $_GET) values, errors, result rows, note
 *   Calculators::currencies()          currency code => label (for the select)
 *   Calculators::isVisible($input, $values)  evaluates an input's show_if
 *
 * Input definition keys: name, label, type ('number'|'radio'|'select'), unit,
 * unit_from (name of the input whose selected option is this field's unit, or
 * null), default, min, max, step, hint, show_if, options, required, integer.
 * show_if maps input names to an allowed value (or list of values); several
 * keys must ALL match. Yes/no toggles are 'radio' inputs with 'yes'/'no'.
 *
 * Deliberate deviations from the Dart source (bugs fixed, intended behaviour):
 *  1. brick: 'Centimeters' inputs are converted to metres (/100). The app used
 *     cm values as metres; only feet were converted.
 *  2. concrete_block: block size fields are inches, as labelled (defaults
 *     16 x 8 x 8 in, about the 40 x 20 x 20 cm the app's subtitle quotes), and are
 *     converted to cm (x 2.54) before the cm-based formula. The app divided the
 *     inch numbers by 100 as if they were cm.
 *  3. brick, concrete_block: mortar volume floored at 0. The app could go negative.
 *  4. boundary_wall: bar height (inches) is converted to feet (/12) before
 *     dividing the wall area (ft²) by the bar face. The app mixed ft and inches.
 *     All four sizes must now be > 0 (the app allowed 0, which divided by zero).
 *  5. cement_concrete: the volume x 1.524 row is labelled "Dry Volume". The app's
 *     variable names were swapped and it showed "Wet Volume". Math unchanged.
 *  6. paint, paint_work: an error is shown when door/window deductions are
 *     at least the paintable area. The app showed negative quantities.
 *  7. paint_area: door/window width and height are required when the count is
 *     > 0. The app's double.parse crashed. Results show "sq ft" / "m²"
 *     instead of the raw option text ("Feet" / "Sq. Meters").
 *  8. Integer counts (quantity, pieces, steps, columns, tubes, doors, windows,
 *     workers) are rounded to whole numbers. In the app int.parse crashed on
 *     "1.5", or int.tryParse silently fell back to 1.
 *  9. plywood_sheets: preset sheet sizes (4x8 ft ...) are always in feet, whatever
 *     the room unit. The app copied "8.0" into the fields in the current unit.
 *     A "Custom" preset allows sizes in the selected unit.
 * 10. concrete_tube, round_pipe: an error is shown when the wall thickness gives
 *     a negative inner diameter. The app only checked inner >= outer.
 * 11. construction_cost: steel is priced per tonne (qty kg / 1000) and wood per
 *     m³ (qty cft x 0.0283168). The app multiplied kg by a per-ton price and cft
 *     by a per-m³ price, overstating those two costs about 1000x and 35x. Region
 *     prices (US/IN/PK/GB default tables, no Firebase) are editable. Empty
 *     falls back to the table and 0 excludes a material. The currency defaults
 *     to the region's currency.
 * 12. roof_area: a Flat roof needs no pitch input (the app required one and
 *     ignored it). The pitch angle must be < 90°.
 * 13. steel_quantity: the area unit defaults to Square Meters. The app's default,
 *     'Cubic Meters', was not an option but was treated as m², so the math is
 *     the same.
 * 14. channel_bar, t_bar, square_bar: "Rate per kg (Optional)" really is
 *     optional (empty means no cost row). The app showed no result until a rate
 *     was typed. Length must be > 0.
 * 15. round_bar: length has its own unit (m or ft, default m), matching the field
 *     label. The app converted length with the diameter unit, so 6 became 0.006 m.
 * 16. round_pipe: displayed wall thickness = (OD - ID) / 2 in mm. The app took
 *     the raw OD text in the selected unit minus the ID in mm.
 * 17. square_bar: total volume is shown with 4 decimals (the app's 2 showed 0.00).
 * 18. i_beam (custom): an error is shown when H <= 2 x flange thickness (no web).
 * 19. Required dimensions must be > 0 everywhere (some screens only checked
 *     "numeric"). Numbers must use '.' as the decimal separator.
 * 20. Converters and the area calculator use US thousands grouping (1,000), per
 *     the site-wide format. The app showed no grouping. The area unit "Yards" is
 *     shown as "sq yards" (the app said "sq yard").
 */
final class Calculators
{
    // ------------------------------------------------------------------
    // Shared tables
    // ------------------------------------------------------------------

    /** Currency code => [symbol, name]. */
    private const CURRENCIES = [
        'USD' => ['$', 'US Dollar'],
        'EUR' => ['€', 'Euro'],
        'GBP' => ['£', 'British Pound'],
        'INR' => ['₹', 'Indian Rupee'],
        'PKR' => ['Rs', 'Pakistani Rupee'],
        'AED' => ['AED', 'UAE Dirham'],
        'SAR' => ['SAR', 'Saudi Riyal'],
    ];

    /** Linear unit => metres, and display names. */
    private const TO_M = ['m' => 1.0, 'cm' => 0.01, 'mm' => 0.001, 'ft' => 0.3048, 'in' => 0.0254, 'yd' => 0.9144];
    private const UNIT_NAMES = ['m' => 'Meters', 'cm' => 'Centimeters', 'mm' => 'Millimeters', 'ft' => 'Feet', 'in' => 'Inches', 'yd' => 'Yards'];

    /** Concrete grades: cement : sand : aggregate. */
    private const GRADES = ['M20' => [1, 1.5, 3], 'M15' => [1, 2, 4], 'M10' => [1, 3, 6], 'M7.5' => [1, 4, 8]];
    private const GRADE_RATIOS = ['M20' => '1:1.5:3', 'M15' => '1:2:4', 'M10' => '1:3:6', 'M7.5' => '1:4:8'];

    /** Calculator id => [tab, method suffix]. Converters/area are handled separately. */
    private const REGISTRY = [
        // Quantity tab (incl. steel_bar and i_beam, which the app shows there)
        'construction_cost' => 'quantity', 'brick' => 'quantity', 'cement_concrete' => 'quantity',
        'plastering' => 'quantity', 'concrete_block' => 'quantity', 'boundary_wall' => 'quantity',
        'paint' => 'quantity', 'steel_bar' => 'quantity', 'excavation' => 'quantity', 'i_beam' => 'quantity',
        'labor_cost' => 'quantity', 'blue_metal' => 'quantity', 'paint_work' => 'quantity',
        'hardware' => 'quantity', 'wood_frame' => 'quantity', 'plywood_sheets' => 'quantity',
        'anti_termite' => 'quantity', 'round_column' => 'quantity', 'stair_case' => 'quantity',
        'top_soil' => 'quantity', 'concrete_tube' => 'quantity', 'roof_area' => 'quantity',
        'carpet_area' => 'quantity', 'paint_area' => 'quantity',
        // Metal tab
        'steel_weight' => 'metal', 'steel_quantity' => 'metal', 'round_bar' => 'metal',
        'beam_steel' => 'metal', 'round_pipe' => 'metal', 'channel_bar' => 'metal',
        't_bar' => 'metal', 'square_bar' => 'metal',
        // Standalone
        'area' => 'area',
    ];

    /** Hub tiles in app order: [id, label, icon, color]. */
    private const HUB = [
        'quantity' => ['Quantity', [
            ['construction_cost', 'Construction Cost', 'calculator', '#2196F3'],
            ['brick', 'Brick', 'home', '#FF9800'],
            ['cement_concrete', 'Cement Concrete', 'cube', '#FFC107'],
            ['plastering', 'Plastering', 'edit', '#F44336'],
            ['concrete_block', 'Concrete Block', 'copy', '#9E9E9E'],
            ['boundary_wall', 'Boundary Wall', 'shield', '#795548'],
            ['paint', 'Paint', 'sparkles', '#FFEB3B'],
            ['steel_bar', 'Steel Bar', 'level', '#607D8B'],
            ['excavation', 'Excavation', 'download', '#4CAF50'],
            ['i_beam', 'I-Beam/Girder', 'layers', '#2196F3'],
            ['labor_cost', 'Labor Cost', 'clock', '#673AB7'],
            ['blue_metal', 'Blue Metal', 'layers', '#3F51B5'],
            ['paint_work', 'Paint Work', 'edit', '#E91E63'],
            ['hardware', 'Hardware', 'settings', '#009688'],
            ['wood_frame', 'Wood Frame', 'chair', '#795548'],
            ['plywood_sheets', 'Plywood Sheets', 'note', '#FF9800'],
            ['anti_termite', 'Anti Termite', 'shield', '#9E9E9E'],
            ['round_column', 'Round Column', 'compass', '#607D8B'],
            ['stair_case', 'Stair Case', 'arrow', '#FF9800'],
            ['top_soil', 'Top Soil', 'globe', '#795548'],
            ['concrete_tube', 'Concrete Tube', 'cube', '#607D8B'],
            ['roof_area', 'Roof Area', 'home', '#F44336'],
            ['carpet_area', 'Carpet Area', 'ruler', '#9E9E9E'],
            ['paint_area', 'Paint Area', 'image', '#FFC107'],
        ]],
        'metal' => ['Metal', [
            ['steel_weight', 'Steel Weight', 'level', '#607D8B'],
            ['steel_quantity', 'Steel Quantity', 'calculator', '#9E9E9E'],
            ['round_bar', 'Round Bar', 'compass', '#607D8B'],
            ['beam_steel', 'Beam Steel', 'layers', '#9E9E9E'],
            ['round_pipe', 'Round Pipe', 'globe', '#607D8B'],
            ['channel_bar', 'Channel Bar', 'copy', '#9E9E9E'],
            ['t_bar', 'T Bar', 'plus', '#607D8B'],
            ['square_bar', 'Square Bar', 'cube', '#9E9E9E'],
        ]],
        'converter' => ['Converter', [
            ['convert_length', 'Distance', 'ruler', '#2196F3'],
            ['convert_area', 'Area', 'layers', '#E91E63'],
            ['convert_volume', 'Volume', 'cube', '#9C27B0'],
            ['convert_weight', 'Weight', 'download', '#FF9800'],
            ['convert_time', 'Time', 'clock', '#4CAF50'],
            ['convert_mass', 'Mass', 'level', '#F44336'],
            ['convert_pressure', 'Pressure', 'settings', '#3F51B5'],
            ['convert_speed', 'Speed', 'arrow', '#FFC107'],
            ['convert_fuel', 'Fuel', 'globe', '#F44336'],
            ['convert_frequency', 'Frequency', 'zap', '#009688'],
            ['convert_angle', 'Angle', 'compass', '#673AB7'],
        ]],
    ];

    /** Alternative ids accepted by find(). */
    private const ALIASES = ['convert_distance' => 'convert_length', 'area_calculator' => 'area', 'aac_termite' => 'anti_termite'];

    /**
     * Linear converters: key => [title, intro unit word, default from, default to,
     * [unit => size of one unit in the base unit]]. Fuel is non-linear (see FUEL).
     */
    private const CONVERTERS = [
        'length' => ['Distance Converter', 'distance', 'Meter', 'Kilometer', [
            'Millimeter' => 0.001, 'Centimeter' => 0.01, 'Meter' => 1.0, 'Kilometer' => 1000.0,
            'Inch' => 0.0254, 'Foot' => 0.3048, 'Yard' => 0.9144, 'Mile' => 1609.344,
            'Nautical Mile' => 1852.0, 'Micron' => 0.000001, 'Nanometer' => 0.000000001,
        ]],
        'area' => ['Area Converter', 'area', 'Square Meter', 'Square Foot', [
            'Square Millimeter' => 0.000001, 'Square Centimeter' => 0.0001, 'Square Meter' => 1.0,
            'Square Kilometer' => 1000000.0, 'Square Inch' => 0.00064516, 'Square Foot' => 0.092903,
            'Square Yard' => 0.836127, 'Acre' => 4046.86, 'Hectare' => 10000.0,
            'Square Mile' => 2589988.11, 'Square Rod' => 25.2929,
        ]],
        'volume' => ['Volume Converter', 'volume', 'Cubic Meter', 'Liter', [
            'Cubic Millimeter' => 0.000000001, 'Cubic Centimeter' => 0.000001, 'Milliliter' => 0.000001,
            'Liter' => 0.001, 'Cubic Meter' => 1.0, 'Cubic Kilometer' => 1000000000.0,
            'Cubic Inch' => 0.000016387, 'Cubic Foot' => 0.028317, 'Cubic Yard' => 0.764555,
            'Fluid Ounce (US)' => 0.000029574, 'Pint (US)' => 0.000473176, 'Quart (US)' => 0.000946353,
            'Gallon (US)' => 0.003785, 'Gallon (UK)' => 0.00454609, 'Barrel (Oil)' => 0.158987,
        ]],
        'weight' => ['Weight Converter', 'weight', 'Kilogram', 'Pound', [
            'Milligram' => 0.000001, 'Gram' => 0.001, 'Kilogram' => 1.0, 'Metric Ton' => 1000.0,
            'Ounce' => 0.028349523125, 'Pound' => 0.45359237, 'Stone' => 6.35029318,
            'Short Ton (US)' => 907.18474, 'Long Ton (UK)' => 1016.0469088, 'Carat' => 0.0002,
            'Grain' => 0.00006479891,
        ]],
        'time' => ['Time Converter', 'time', 'Hour', 'Minute', [
            'Nanosecond' => 0.000000001, 'Microsecond' => 0.000001, 'Millisecond' => 0.001,
            'Second' => 1.0, 'Minute' => 60.0, 'Hour' => 3600.0, 'Day' => 86400.0, 'Week' => 604800.0,
            'Month' => 2629746.0, 'Year' => 31556952.0, 'Decade' => 315569520.0, 'Century' => 3155695200.0,
        ]],
        'mass' => ['Mass Converter', 'mass', 'Kilogram', 'Gram', [
            'Microgram' => 0.000000001, 'Milligram' => 0.000001, 'Centigram' => 0.00001,
            'Decigram' => 0.0001, 'Gram' => 0.001, 'Dekagram' => 0.01, 'Hectogram' => 0.1,
            'Kilogram' => 1.0, 'Quintal' => 100.0, 'Metric Ton' => 1000.0, 'Ounce' => 0.028349523125,
            'Pound' => 0.45359237, 'Stone' => 6.35029318, 'Short Ton (US)' => 907.18474,
            'Long Ton (UK)' => 1016.0469088, 'Carat' => 0.0002, 'Grain' => 0.00006479891,
        ]],
        'pressure' => ['Pressure Converter', 'pressure', 'Bar', 'Pascal', [
            'Pascal' => 1.0, 'Kilopascal' => 1000.0, 'Megapascal' => 1000000.0, 'Millibar' => 100.0,
            'Bar' => 100000.0, 'PSI' => 6894.757293168, 'Atmosphere' => 101325.0,
            'Torr' => 133.322368421, 'mmHg' => 133.322387415, 'inHg' => 3386.389,
            'mmH2O' => 9.80665, 'kgf/cm²' => 98066.5,
        ]],
        'speed' => ['Speed Converter', 'speed', 'Kilometer/Hour', 'Mile/Hour', [
            'Meter/Second' => 1.0, 'Kilometer/Hour' => 0.277777778, 'Kilometer/Second' => 1000.0,
            'Mile/Hour' => 0.44704, 'Mile/Second' => 1609.344, 'Foot/Second' => 0.3048,
            'Foot/Minute' => 0.00508, 'Knot' => 0.514444444, 'Mach' => 343.0,
            'Speed of Sound' => 343.0, 'Speed of Light' => 299792458.0,
        ]],
        'fuel' => ['Fuel Converter', 'fuel economy', 'Liter/100km', 'Mile/Gallon (US)', []],
        'frequency' => ['Frequency Converter', 'frequency', 'Hertz', 'Kilohertz', [
            'Nanohertz' => 0.000000001, 'Microhertz' => 0.000001, 'Millihertz' => 0.001, 'Hertz' => 1.0,
            'Kilohertz' => 1000.0, 'Megahertz' => 1000000.0, 'Gigahertz' => 1000000000.0,
            'Terahertz' => 1000000000000.0, 'Cycle/Second' => 1.0, 'Revolution/Minute' => 0.016666667,
            'Revolution/Second' => 1.0,
        ]],
        'angle' => ['Angle Converter', 'angle', 'Degree', 'Radian', [
            'Degree' => 1.0, 'Radian' => 57.29577951308232, 'Gradian' => 0.9, 'Arcminute' => 0.016666667,
            'Arcsecond' => 0.000277778, 'Turn' => 360.0, 'Milliradian' => 0.057295779513082,
        ]],
    ];

    /** Fuel units: name => [type, factor]; base is Liter/100km. */
    private const FUEL = [
        'Liter/100km' => ['consumption', 1.0],
        'Kilometer/Liter' => ['efficiency', 100.0],
        'Mile/Gallon (US)' => ['efficiency', 235.214583],
        'Mile/Gallon (UK)' => ['efficiency', 282.481053],
        'Mile/Liter' => ['efficiency', 62.1371192],
        'Gallon (US)/100mi' => ['consumption', 2.352145833],
        'Gallon (UK)/100mi' => ['consumption', 2.824809363],
    ];

    /** Construction cost: material => [qty per sq ft, qty unit, label, price unit]. */
    private const CC_MATERIALS = [
        'cement' => [0.4, 'bags', 'Cement', 'bag'],
        'sand' => [0.02, 'tons', 'Sand', 'ton'],
        'aggregate' => [0.015, 'tons', 'Aggregate', 'ton'],
        'steel' => [4.0, 'kg', 'Steel', 'ton'],
        'brick' => [8.0, 'pieces', 'Brick', 'piece'],
        'concrete_block' => [0.5, 'pieces', 'Concrete Block', 'piece'],
        'paint' => [0.18, 'liters', 'Paint', 'liter'],
        'plaster' => [0.01, 'bags', 'Plaster', 'bag'],
        'tile' => [1.1, 'sq ft', 'Tile', 'sq ft'],
        'wood' => [0.5, 'cft', 'Wood', 'm³'],
    ];

    /** Default prices (local currency) from firebase_pricing_service.dart. */
    private const CC_PRICES = [
        'US' => ['United States', 'USD', ['cement' => 12.0, 'sand' => 45.0, 'aggregate' => 50.0, 'steel' => 850.0, 'brick' => 0.85, 'concrete_block' => 3.5, 'paint' => 35.0, 'plaster' => 18.0, 'tile' => 25.0, 'wood' => 1200.0]],
        'IN' => ['India', 'INR', ['cement' => 420.0, 'sand' => 3500.0, 'aggregate' => 3200.0, 'steel' => 65000.0, 'brick' => 8.0, 'concrete_block' => 45.0, 'paint' => 350.0, 'plaster' => 280.0, 'tile' => 450.0, 'wood' => 45000.0]],
        'PK' => ['Pakistan', 'PKR', ['cement' => 1400.0, 'sand' => 20000.0, 'aggregate' => 24000.0, 'steel' => 280000.0, 'brick' => 15.0, 'concrete_block' => 80.0, 'paint' => 1800.0, 'plaster' => 450.0, 'tile' => 180.0, 'wood' => 95000.0]],
        'GB' => ['United Kingdom', 'GBP', ['cement' => 6.5, 'sand' => 35.0, 'aggregate' => 40.0, 'steel' => 650.0, 'brick' => 0.75, 'concrete_block' => 3.2, 'paint' => 22.0, 'plaster' => 8.5, 'tile' => 28.0, 'wood' => 950.0]],
    ];

    /** Labor trades: key => [name, base USD/hr]. */
    private const TRADES = [
        'general_laborer' => ['General Laborer', 20.0], 'carpenter' => ['Carpenter', 33.5],
        'electrician' => ['Electrician', 41.5], 'plumber' => ['Plumber', 38.0],
        'hvac' => ['HVAC Technician', 37.5], 'roofer' => ['Roofer', 26.5],
        'painter' => ['Painter', 26.5], 'mason' => ['Mason', 30.0],
        'welder' => ['Welder', 35.0], 'concrete_worker' => ['Concrete Worker', 25.0],
    ];

    /** Hardware & fittings share of construction cost (%). */
    private const HARDWARE_PCT = ['Windows' => 3.0, 'Doors' => 3.4, 'Plumbing' => 5.5, 'Electrical' => 6.8, 'Sanitary' => 4.1];

    /** Steel ratios kg per m³ of concrete. */
    private const STEEL_MEMBERS = [
        'footing' => ['Footing', 80], 'beam' => ['Beam', 160], 'column' => ['Column', 110],
        'slab' => ['Slab', 80], 'staircase' => ['Staircase', 85], 'lintel' => ['Lintel/Chhajja', 50],
        'retaining_wall' => ['Retaining Wall', 60],
    ];

    /** ISMB weights (kg/m): beam_steel and i_beam use slightly different tables in the app. */
    private const ISMB_BEAM_STEEL = ['ISMB 100' => 11.5, 'ISMB 125' => 13.3, 'ISMB 150' => 15.0, 'ISMB 175' => 19.6, 'ISMB 200' => 25.4, 'ISMB 225' => 31.2, 'ISMB 250' => 37.3, 'ISMB 300' => 46.0, 'ISMB 350' => 52.4, 'ISMB 400' => 61.5, 'ISMB 450' => 72.4, 'ISMB 500' => 86.9, 'ISMB 550' => 104.0, 'ISMB 600' => 122.6];
    private const ISMB_I_BEAM = ['ISMB 100' => 11.5, 'ISMB 125' => 13.7, 'ISMB 150' => 14.9, 'ISMB 175' => 18.0, 'ISMB 200' => 25.4, 'ISMB 225' => 31.2, 'ISMB 250' => 37.3, 'ISMB 300' => 46.1, 'ISMB 350' => 52.4, 'ISMB 400' => 61.6, 'ISMB 450' => 72.4, 'ISMB 500' => 86.9, 'ISMB 550' => 101.8, 'ISMB 600' => 122.6];

    /** ISMC channels: size => [kg/m, height, width, web t, flange t] (mm). */
    private const ISMC = [
        'ISMC 75' => [7.14, 75, 40, 4.8, 7.5], 'ISMC 100' => [9.56, 100, 50, 5.0, 7.7],
        'ISMC 125' => [13.1, 125, 65, 5.3, 8.2], 'ISMC 150' => [16.8, 150, 75, 5.7, 9.0],
        'ISMC 175' => [19.6, 175, 75, 6.0, 10.2], 'ISMC 200' => [22.3, 200, 75, 6.2, 11.4],
        'ISMC 250' => [34.2, 250, 82, 9.0, 14.1], 'ISMC 300' => [36.3, 300, 90, 7.8, 13.6],
        'ISMC 400' => [50.1, 400, 100, 8.8, 15.3],
    ];

    /** Tee sections: type => [description, [designation => [depth mm, flange mm, kg/m]]]. */
    private const TEES = [
        'ISNT' => ['Indian Standard Narrow Tee', [
            'ISNT 20' => [20, 20, 0.9], 'ISNT 30' => [30, 30, 1.4], 'ISNT 40' => [40, 40, 3.5],
            'ISNT 50' => [50, 50, 4.5], 'ISNT 60' => [60, 60, 5.4], 'ISNT 80' => [80, 80, 9.6],
            'ISNT 100' => [100, 100, 15.0], 'ISNT 150' => [150, 150, 22.0],
        ]],
        'ISHT' => ['Indian Standard Heavy Tee', [
            'ISHT 75' => [75, 75, 15.3], 'ISHT 100' => [100, 100, 20.0],
            'ISHT 125' => [125, 125, 27.4], 'ISHT 150' => [150, 150, 29.4],
        ]],
        'ISST' => ['Indian Standard Standard Tee', [
            'ISST 100' => [100, 100, 8.1], 'ISST 150' => [150, 150, 15.7],
            'ISST 200' => [200, 200, 28.4], 'ISST 250' => [250, 250, 37.5],
        ]],
    ];

    private const SQUARE_BAR_MATERIALS = ['Mild Steel' => 7850, 'Stainless Steel' => 8000, 'Carbon Steel' => 7840, 'Alloy Steel' => 7850, 'Tool Steel' => 7850];
    private const PIPE_MATERIALS = ['Mild Steel' => 7850, 'Carbon Steel' => 7840, 'Stainless Steel' => 8030, 'Galvanized Steel' => 7850, 'Copper' => 8960, 'Aluminum' => 2700, 'Brass' => 8500];

    private const YES_NO = ['yes' => 'Yes', 'no' => 'No'];

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /** Hub for /app/calculators: tabs in app order. */
    public static function hub(): array
    {
        $tabs = [];
        foreach (self::HUB as $tabId => [$name, $tiles]) {
            $out = [];
            foreach ($tiles as [$id, $label, $icon, $color]) {
                $out[] = ['id' => $id, 'label' => $label, 'icon' => $icon, 'color' => $color];
            }
            $tabs[] = ['id' => $tabId, 'name' => $name, 'tiles' => $out];
        }
        return ['tabs' => $tabs];
    }

    /** Currency code => label, for the currency select. */
    public static function currencies(): array
    {
        $out = [];
        foreach (self::CURRENCIES as $code => [$sym, $name]) {
            $out[$code] = $code . ' (' . $sym . ') ' . $name;
        }
        return $out;
    }

    /** One calculator definition, or null. */
    public static function find(string $id): ?array
    {
        static $cache = [];
        $id = self::ALIASES[$id] ?? $id;
        if (array_key_exists($id, $cache)) {
            return $cache[$id];
        }
        $def = null;
        if (str_starts_with($id, 'convert_')) {
            $key = substr($id, 8);
            if (isset(self::CONVERTERS[$key])) {
                $def = self::defConverter($key);
            }
        } elseif (isset(self::REGISTRY[$id])) {
            $def = call_user_func([self::class, 'def' . self::method($id)]);
        }
        if ($def !== null) {
            [$def['icon'], $def['color']] = self::tileLook($id);
        }
        return $cache[$id] = $def;
    }

    /** Run a calculator with request values ($_GET). */
    public static function compute(array $calc, array $query): array
    {
        $values = [];
        foreach ($calc['inputs'] as $in) {
            $values[$in['name']] = self::readValue($in, $query[$in['name']] ?? null);
        }

        // Currency: validated against the list; construction_cost follows its region.
        if (!empty($calc['currency'])) {
            $raw = $query['currency'] ?? null;
            $default = $calc['id'] === 'construction_cost' ? self::CC_PRICES[$values['region']][1] : 'USD';
            $values['currency'] = is_string($raw) && isset(self::CURRENCIES[$raw]) ? $raw : $default;
        }
        $currency = $values['currency'] ?? 'USD';

        $submitted = isset($query['calc'])
            || $calc['tab'] === 'converter'                     // converters are live, like the app
            || ($calc['tab'] === 'area' && isset($query['mode']));

        $result = ['submitted' => $submitted, 'values' => $values, 'errors' => [], 'rows' => [], 'note' => null];
        if (!$submitted) {
            return $result;
        }

        // Parse and validate visible number inputs.
        $nums = [];
        $errors = [];
        foreach ($calc['inputs'] as $in) {
            if ($in['type'] !== 'number') {
                continue;
            }
            $name = $in['name'];
            $nums[$name] = null;
            if (!self::isVisible($in, $values)) {
                continue;
            }
            $raw = $values[$name];
            if ($raw === '') {
                if ($in['required']) {
                    $errors[$name] = 'Please enter a value.';
                } elseif ($in['fallback'] !== null) {
                    $nums[$name] = $in['fallback'];
                }
                continue;
            }
            if (strlen($raw) > 30 || !preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $raw)) {
                $errors[$name] = 'Enter a valid number (use "." for decimals).';
                continue;
            }
            $f = (float) $raw;
            if ($in['integer']) {
                $f = round($f);
                $values[$name] = number_format($f, 0, '.', '');
            }
            if ($in['rule'] === 'pos' && $f <= 0) {
                $errors[$name] = 'Must be greater than zero.';
            } elseif ($in['min'] !== null && $f < $in['min']) {
                $errors[$name] = $in['min'] == 0 ? 'Cannot be negative.' : 'Must be at least ' . self::plain($in['min']) . '.';
            } elseif ($in['max'] !== null && $f > $in['max']) {
                $errors[$name] = 'Must be at most ' . self::plain($in['max']) . '.';
            } else {
                $nums[$name] = $f;
            }
        }
        $result['values'] = $values;

        if ($errors) {
            $result['errors'] = $errors;
            return $result;
        }

        if ($calc['tab'] === 'converter') {
            $out = self::runConverter(substr($calc['id'], 8), $nums, $values);
        } else {
            $out = call_user_func([self::class, 'run' . self::method($calc['id'])], $nums, $values, $currency);
        }
        if (!empty($out['errors'])) {
            $result['errors'] = $out['errors'];
            return $result;
        }
        $result['rows'] = $out['rows'] ?? [];
        $result['note'] = $out['note'] ?? null;
        return $result;
    }

    /** Is an input shown for the current values (evaluates show_if; all keys must match)? */
    public static function isVisible(array $input, array $values): bool
    {
        if (empty($input['show_if'])) {
            return true;
        }
        foreach ($input['show_if'] as $key => $allowed) {
            $allowed = array_map('strval', (array) $allowed);
            if (!in_array((string) ($values[$key] ?? ''), $allowed, true)) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Definition builders
    // ------------------------------------------------------------------

    private static function calc(string $id, string $title, string $intro, array $inputs, bool $currency = false): array
    {
        return ['id' => $id, 'title' => $title, 'intro' => $intro, 'tab' => self::REGISTRY[$id], 'inputs' => $inputs, 'currency' => $currency];
    }

    /**
     * Number input. Options: default, required (default true), rule
     * ('pos' >0 [default] | 'nonneg' >=0 | 'any'), min, max, step, integer,
     * hint, show_if, unit_from, fallback (value used when optional and empty).
     */
    private static function num(string $name, string $label, ?string $unit = null, array $o = []): array
    {
        $rule = $o['rule'] ?? 'pos';
        $integer = $o['integer'] ?? false;
        $default = isset($o['default']) ? (string) $o['default'] : '';
        $required = $o['required'] ?? true;
        $fallback = $o['fallback'] ?? ($default !== '' ? (float) $default : null);
        return [
            'name' => $name, 'label' => $label, 'type' => 'number', 'unit' => $unit,
            'unit_from' => $o['unit_from'] ?? null, 'default' => $default,
            'min' => $o['min'] ?? ($rule === 'any' ? null : 0), 'max' => $o['max'] ?? null,
            'step' => $o['step'] ?? ($integer ? '1' : 'any'), 'hint' => $o['hint'] ?? null,
            'show_if' => $o['show_if'] ?? null, 'options' => null,
            'required' => $required, 'integer' => $integer, 'rule' => $rule,
            'fallback' => $required ? null : $fallback,
        ];
    }

    private static function choice(string $type, string $name, string $label, array $options, string $default, ?array $showIf): array
    {
        return [
            'name' => $name, 'label' => $label, 'type' => $type, 'unit' => null, 'unit_from' => null,
            'default' => $default, 'min' => null, 'max' => null, 'step' => null, 'hint' => null,
            'show_if' => $showIf, 'options' => $options, 'required' => true, 'integer' => false,
            'rule' => 'any', 'fallback' => null,
        ];
    }

    private static function radio(string $name, string $label, array $options, string $default, ?array $showIf = null): array
    {
        return self::choice('radio', $name, $label, $options, $default, $showIf);
    }

    private static function select(string $name, string $label, array $options, string $default, ?array $showIf = null): array
    {
        return self::choice('select', $name, $label, $options, $default, $showIf);
    }

    private static function yesNo(string $name, string $label, string $default, ?array $showIf = null): array
    {
        return self::radio($name, $label, self::YES_NO, $default, $showIf);
    }

    /** Unit options for the given linear unit keys, in that order. */
    private static function units(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::UNIT_NAMES[$k];
        }
        return $out;
    }

    private static function gradeOptions(): array
    {
        $out = [];
        foreach (self::GRADE_RATIOS as $g => $r) {
            $out[$g] = $g . ' (' . $r . ')';
        }
        return $out;
    }

    private static function opts(array $values, string $suffix = ''): array
    {
        $out = [];
        foreach ($values as $v) {
            $out[(string) $v] = $v . $suffix;
        }
        return $out;
    }

    /** Dimension input whose unit follows the 'unit' selector. */
    private static function dim(string $name, string $label, array $o = []): array
    {
        return self::num($name, $label, null, $o + ['unit_from' => 'unit']);
    }

    // ------------------------------------------------------------------
    // Formatting & small helpers
    // ------------------------------------------------------------------

    /** US number format with fixed decimals. */
    private static function fmt(float $x, int $dp = 2): string
    {
        if (!is_finite($x)) {
            return '—';
        }
        $s = number_format($x, $dp, '.', ',');
        return preg_match('/^-0(\.0+)?$/', $s) ? substr($s, 1) : $s;
    }

    /** Money with the selected currency symbol. */
    private static function money(float $x, string $cur): string
    {
        $sym = self::CURRENCIES[$cur][0] ?? '$';
        $sep = preg_match('/^[A-Za-z]/', $sym) ? ' ' : '';
        $s = self::fmt(abs($x), 2);
        return ($x < 0 && $s !== '0.00' ? '-' : '') . $sym . $sep . $s;
    }

    /** Compact number without trailing zeros (for labels like "Must be at most 15"). */
    private static function plain(float|int $x): string
    {
        $s = number_format((float) $x, 6, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    }

    /** Strip trailing zeros of a fixed-decimal string ("12.50" -> "12.5", "100.00" -> "100"). */
    private static function trimZeros(string $s): string
    {
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    private static function row(string $label, string $value, string $unit = ''): array
    {
        return ['label' => $label, 'value' => $value, 'unit' => $unit];
    }

    /** "1:6" -> [1.0, 6.0]. */
    private static function ratio(string $r): array
    {
        [$a, $b] = array_map('floatval', explode(':', $r));
        return [$a, $b];
    }

    /** Value of a linear dimension in metres. */
    private static function m(?float $value, string $unit): float
    {
        return (float) $value * (self::TO_M[$unit] ?? 1.0);
    }

    /** Studly method suffix from an id ("cement_concrete" -> "CementConcrete"). */
    private static function method(string $id): string
    {
        return str_replace('_', '', ucwords($id, '_'));
    }

    /** Icon and color of a calculator's hub tile (or a default). */
    private static function tileLook(string $id): array
    {
        foreach (self::HUB as [, $tiles]) {
            foreach ($tiles as [$tid, , $icon, $color]) {
                if ($tid === $id) {
                    return [$icon, $color];
                }
            }
        }
        return ['ruler', '#4CAF50'];
    }

    /** Current value of one input from the query (string), falling back to its default. */
    private static function readValue(array $in, mixed $raw): string
    {
        $v = is_string($raw) || is_int($raw) || is_float($raw) ? trim((string) $raw) : null;
        if ($in['type'] === 'number') {
            return $v === null ? $in['default'] : substr($v, 0, 40);
        }
        return $v !== null && array_key_exists($v, $in['options']) ? $v : $in['default'];
    }

    /** Cement / sand / aggregate rows for a dry volume and grade (shared by concrete calculators). */
    private static function concreteMaterials(float $dry, string $grade, float $sandDensity = 1.55, float $aggDensity = 1.35): array
    {
        [$c, $s, $a] = self::GRADES[$grade];
        $t = $c + $s + $a;
        return [
            self::row('Cement', self::fmt($dry * ($c / $t) / 0.035), 'bags'),
            self::row('Sand', self::fmt($dry * ($s / $t) * $sandDensity), 'tons'),
            self::row('Aggregate', self::fmt($dry * ($a / $t) * $aggDensity), 'tons'),
        ];
    }

    // ==================================================================
    // QUANTITY CALCULATORS
    // ==================================================================

    // ---- Brick --------------------------------------------------------
    private static function defBrick(): array
    {
        return self::calc('brick', 'Brick Calculator', 'Estimate the bricks, cement and sand needed for a brick wall.', [
            self::radio('unit', 'Unit', self::units(['cm', 'ft']), 'cm'),
            self::dim('length', 'Length'),
            self::dim('height', 'Height'),
            self::select('thickness', 'Wall Thickness', ['10' => '10 cm', '23' => '23 cm', '30' => '30 cm'], '23'),
            self::select('mortar_ratio', 'Mortar Ratio (cement : sand)', self::opts(['1:3', '1:4', '1:5', '1:6', '1:8']), '1:6'),
            self::num('brick_l', 'Brick Length', 'cm', ['default' => '19', 'required' => false, 'hint' => 'Brick size (optional)']),
            self::num('brick_w', 'Brick Width', 'cm', ['default' => '9', 'required' => false]),
            self::num('brick_h', 'Brick Height', 'cm', ['default' => '9', 'required' => false]),
        ]);
    }

    private static function runBrick(array $n, array $v): array
    {
        $L = self::m($n['length'], $v['unit']);
        $H = self::m($n['height'], $v['unit']);
        $T = (float) $v['thickness'] / 100;
        $volume = $L * $H * $T;
        [$bl, $bw, $bh] = [$n['brick_l'], $n['brick_w'], $n['brick_h']];
        $withMortar = (($bl + 1) / 100) * (($bw + 1) / 100) * (($bh + 1) / 100);   // 1 cm joint
        $needed = ceil($volume / $withMortar);
        $total = ceil($needed * 1.15);                                             // 15% wastage
        $actual = ($bl / 100) * ($bw / 100) * ($bh / 100);
        $mortar = max(0.0, ($volume - $needed * $actual) * 1.25);                  // dry volume
        [$c, $s] = self::ratio($v['mortar_ratio']);
        return [
            'rows' => [
                self::row('Total Bricks', self::fmt($total, 0), 'pieces'),
                self::row('Masonry Volume', self::fmt($volume), 'm³'),
                self::row('Cement', self::fmt(($mortar * $c / ($c + $s)) / 0.035), 'bags'),
                self::row('Sand', self::fmt(($mortar * $s / ($c + $s)) * 1.5), 'tons'),
            ],
            'note' => 'Includes 15% wastage on bricks and a 1 cm mortar joint. Mortar ratio ' . $v['mortar_ratio'] . ' (cement : sand).',
        ];
    }

    // ---- Cement concrete ---------------------------------------------
    private static function defCementConcrete(): array
    {
        return self::calc('cement_concrete', 'Cement Concrete Calculator', 'Work out cement, sand and aggregate for a concrete slab or pour.', [
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width'),
            self::dim('depth', 'Depth / Thickness'),
            self::select('grade', 'Concrete Grade', self::gradeOptions(), 'M20'),
        ]);
    }

    private static function runCementConcrete(array $n, array $v): array
    {
        $volume = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']) * self::m($n['depth'], $v['unit']);
        $dry = $volume * 1.524;
        return [
            'rows' => array_merge([
                self::row('Volume', self::fmt($volume), 'm³'),
                self::row('Dry Volume', self::fmt($dry), 'm³'),
            ], self::concreteMaterials($dry, $v['grade'])),
            'note' => 'Grade ' . $v['grade'] . ' (' . self::GRADE_RATIOS[$v['grade']] . '). Dry volume = wet volume × 1.524. 1 bag of cement = 0.035 m³.',
        ];
    }

    // ---- Plastering --------------------------------------------------
    private static function defPlastering(): array
    {
        return self::calc('plastering', 'Plastering Calculator', 'Find the cement and sand needed to plaster a wall or ceiling.', [
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width / Height'),
            self::select('thickness', 'Plaster Thickness', self::opts([12, 15, 18, 20], ' mm'), '12'),
            self::select('mortar_ratio', 'Mortar Ratio', ['1:3' => 'C.M 1:3', '1:4' => 'C.M 1:4', '1:5' => 'C.M 1:5', '1:6' => 'C.M 1:6'], '1:4'),
        ]);
    }

    private static function runPlastering(array $n, array $v): array
    {
        $area = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']);
        $wet = $area * (float) $v['thickness'] / 1000;
        $dry = $wet * 1.30 * 1.25;                    // joint fill + dry volume factor
        [$c, $s] = self::ratio($v['mortar_ratio']);
        $bags = ($dry * $c / ($c + $s)) / 0.035;
        $sandKg = ($dry * $s / ($c + $s)) * 1550;
        return [
            'rows' => [
                self::row('Total Plaster Area', self::fmt($area), 'm²'),
                self::row('Cement', self::fmt($bags), 'bags'),
                self::row('Cement (weight)', self::fmt($bags * 50), 'kg'),
                self::row('Sand', self::fmt($sandKg / 1000), 'tons'),
                self::row('Sand (weight)', self::fmt($sandKg), 'kg'),
            ],
            'note' => 'Thickness: ' . $v['thickness'] . ' mm | Ratio: ' . $v['mortar_ratio'] . '. 1 bag of cement = 50 kg.',
        ];
    }

    // ---- Concrete block ----------------------------------------------
    private static function defConcreteBlock(): array
    {
        return self::calc('concrete_block', 'Concrete Block Calculator', 'Count the concrete blocks and mortar materials for a block wall.', [
            self::radio('unit', 'Unit', self::units(['cm', 'ft']), 'cm'),
            self::dim('length', 'Length'),
            self::dim('height', 'Height'),
            self::select('thickness', 'Wall Thickness', ['10' => '10 cm', '23' => '23 cm', '30' => '30 cm'], '23'),
            self::select('mortar_ratio', 'Mortar Ratio (cement : sand)', self::opts(['1:3', '1:4', '1:5', '1:6', '1:8']), '1:6'),
            self::num('block_l', 'Block Length', 'in', ['default' => '16', 'required' => false, 'hint' => 'Block size (optional), default 16 × 8 × 8 in (≈ 40 × 20 × 20 cm)']),
            self::num('block_w', 'Block Width', 'in', ['default' => '8', 'required' => false]),
            self::num('block_h', 'Block Height', 'in', ['default' => '8', 'required' => false]),
        ]);
    }

    private static function runConcreteBlock(array $n, array $v): array
    {
        $volume = self::m($n['length'], $v['unit']) * self::m($n['height'], $v['unit']) * ((float) $v['thickness'] / 100);
        // Block sizes are inches; the formula works in cm (fix, see deviation 2).
        [$bl, $bw, $bh] = [$n['block_l'] * 2.54, $n['block_w'] * 2.54, $n['block_h'] * 2.54];
        $withMortar = (($bl + 1.5) / 100) * (($bw + 1.5) / 100) * (($bh + 1.5) / 100);   // 1.5 cm joint
        $blocks = ceil($volume / $withMortar);
        $actual = ($bl / 100) * ($bw / 100) * ($bh / 100);
        $mortar = max(0.0, ($volume - $blocks * $actual) * 1.15 * 1.25);
        [$c, $s] = self::ratio($v['mortar_ratio']);
        return [
            'rows' => [
                self::row('Concrete Blocks', self::fmt($blocks, 0), 'pieces'),
                self::row('Masonry Volume', self::fmt($volume), 'm³'),
                self::row('Cement', self::fmt(($mortar * $c / ($c + $s)) / 0.035), 'bags'),
                self::row('Sand', self::fmt(($mortar * $s / ($c + $s)) * 1.55), 'tons'),
            ],
            'note' => 'Includes a 1.5 cm mortar joint and 15% mortar wastage. Mortar ratio ' . $v['mortar_ratio'] . '.',
        ];
    }

    // ---- Boundary wall -----------------------------------------------
    private static function defBoundaryWall(): array
    {
        return self::calc('boundary_wall', 'Boundary Wall Calculator', 'Estimate precast horizontal bars and vertical posts for a boundary wall.', [
            self::num('length', 'Wall Length', 'ft'),
            self::num('height', 'Wall Height', 'ft'),
            self::num('bar_length', 'Horizontal Bar Length', 'ft', ['default' => '4']),
            self::num('bar_height', 'Horizontal Bar Height', 'in', ['default' => '10']),
            self::yesNo('include_spacing', 'Include Spacing', 'yes'),
        ]);
    }

    private static function runBoundaryWall(array $n, array $v): array
    {
        [$len, $h, $bl] = [$n['length'], $n['height'], $n['bar_length']];
        $bh = $n['bar_height'] / 12;   // inches -> feet (fix, see deviation 4)
        if ($v['include_spacing'] === 'yes') {
            $actual = $len - ($len * 0.50 / $bl);       // 0.5 ft gap per post
            $bars = ($actual * $h) / ($bl * $bh);
        } else {
            $bars = ($len * $h) / ($bl * $bh);
        }
        $posts = $len / $bl + 1;
        return [
            'rows' => [
                self::row('Horizontal Bars Required', self::fmt(round($bars), 0), 'bars'),
                self::row('Vertical Posts Required', self::fmt(round($posts), 0), 'posts'),
            ],
            'note' => $v['include_spacing'] === 'yes' ? 'Calculated with 0.5 ft spacing for each post.' : 'Calculated without spacing.',
        ];
    }

    // ---- Paint ---------------------------------------------------------
    private static function defPaint(): array
    {
        return self::calc('paint', 'Paint Calculator', 'Estimate paint, primer and putty from the carpet area of a room.', [
            self::num('carpet_area', 'Carpet Area', 'sq ft'),
            self::num('door_width', 'Door Width', 'ft', ['default' => '3', 'rule' => 'nonneg']),
            self::num('door_height', 'Door Height', 'ft', ['default' => '7', 'rule' => 'nonneg']),
            self::num('door_qty', 'Number of Doors', null, ['default' => '1', 'rule' => 'nonneg', 'integer' => true]),
            self::num('window_width', 'Window Width', 'ft', ['default' => '3', 'rule' => 'nonneg']),
            self::num('window_height', 'Window Height', 'ft', ['default' => '4', 'rule' => 'nonneg']),
            self::num('window_qty', 'Number of Windows', null, ['default' => '1', 'rule' => 'nonneg', 'integer' => true]),
            self::radio('coats', 'Number of Coats', ['1' => '1 Coat (Repainting)', '2' => '2 Coats (Fresh)'], '2'),
        ]);
    }

    private static function runPaint(array $n, array $v): array
    {
        $initial = $n['carpet_area'] * 3.5;   // walls + ceiling
        $doors = $n['door_width'] * $n['door_height'] * $n['door_qty'];
        $windows = $n['window_width'] * $n['window_height'] * $n['window_qty'];
        $actual = $initial - $doors - $windows;
        if ($actual <= 0) {
            return ['errors' => ['carpet_area' => 'Door and window deductions are larger than the paintable area.']];
        }
        $coats = (int) $v['coats'];
        return [
            'rows' => [
                self::row('Total Wall & Ceiling Area', self::fmt($initial), 'sq ft'),
                self::row('Door Area Deducted', self::fmt($doors), 'sq ft'),
                self::row('Window Area Deducted', self::fmt($windows), 'sq ft'),
                self::row('Actual Paint Area', self::fmt($actual), 'sq ft'),
                self::row('Paint Required (' . $coats . ($coats === 1 ? ' coat' : ' coats') . ')', self::fmt(($actual / 100) * $coats), 'liters'),
                self::row('Primer Required', self::fmt($actual / 100), 'liters'),
                self::row('Putty Required', self::fmt(($actual / 100) * 2.5), 'kg'),
            ],
            'note' => 'Wall & ceiling area = carpet area × 3.5. Coverage varies by paint type and surface.',
        ];
    }

    // ---- Paint work ----------------------------------------------------
    private static function defPaintWork(): array
    {
        return self::calc('paint_work', 'Paint Work Calculator', 'Paint, primer and putty quantities in liters and gallons.', [
            self::radio('unit', 'Area Unit', ['sqft' => 'Square Feet', 'sqm' => 'Square Meters'], 'sqft'),
            self::num('carpet_area', 'Carpet Area', null, ['unit_from' => 'unit']),
            self::radio('coats', 'Number of Coats', ['1' => '1 Coat (Repainting)', '2' => '2 Coats (Fresh)', '3' => '3 Coats (Premium)'], '2'),
            self::num('door_width', 'Door Width', 'ft', ['default' => '3', 'rule' => 'nonneg']),
            self::num('door_height', 'Door Height', 'ft', ['default' => '7', 'rule' => 'nonneg']),
            self::num('door_count', 'Number of Doors', null, ['default' => '0', 'rule' => 'nonneg', 'integer' => true]),
            self::num('window_width', 'Window Width', 'ft', ['default' => '4', 'rule' => 'nonneg']),
            self::num('window_height', 'Window Height', 'ft', ['default' => '4', 'rule' => 'nonneg']),
            self::num('window_count', 'Number of Windows', null, ['default' => '0', 'rule' => 'nonneg', 'integer' => true]),
        ]);
    }

    private static function runPaintWork(array $n, array $v): array
    {
        $carpet = $n['carpet_area'] * ($v['unit'] === 'sqm' ? 10.7639 : 1.0);
        $paintArea = $carpet * 3.5;
        $doors = $n['door_width'] * $n['door_height'] * $n['door_count'];
        $windows = $n['window_width'] * $n['window_height'] * $n['window_count'];
        $actual = $paintArea - $doors - $windows;
        if ($actual <= 0) {
            return ['errors' => ['carpet_area' => 'Door and window deductions are larger than the paintable area.']];
        }
        $paintL = ($actual / 100) * (int) $v['coats'];
        return [
            'rows' => [
                self::row('Total Paint Area', self::fmt($paintArea), 'sq ft'),
                self::row('Deductions (Doors)', self::fmt($doors), 'sq ft'),
                self::row('Deductions (Windows)', self::fmt($windows), 'sq ft'),
                self::row('Actual Paint Area', self::fmt($actual), 'sq ft'),
                self::row('Paint', self::fmt($paintL), 'L'),
                self::row('Paint', self::fmt($paintL * 0.264172), 'gal'),
                self::row('Primer', self::fmt($actual / 100), 'L'),
                self::row('Putty', self::fmt($actual / 40), 'kg'),
            ],
            'note' => 'Coverage: 100 sq ft per liter of paint or primer, 40 sq ft per kg of putty. Results are in sq ft.',
        ];
    }

    // ---- Paint area ----------------------------------------------------
    private static function defPaintArea(): array
    {
        $walls = ['mode' => 'walls'];
        $inputs = [
            self::radio('mode', 'Calculation Mode', ['carpet' => 'From Carpet Area', 'walls' => 'Wall by Wall'], 'carpet'),
            self::radio('unit', 'Unit of Measurement', ['ft' => 'Feet', 'm' => 'Meters'], 'ft'),
            self::yesNo('include_ceiling', 'Include Ceiling', 'no'),
            self::num('carpet_area', 'Carpet Area', null, ['unit_from' => 'unit', 'rule' => 'nonneg', 'show_if' => ['mode' => 'carpet'], 'hint' => 'sq ft or m²']),
            self::select('num_walls', 'Number of Walls', ['1' => '1 Wall', '2' => '2 Walls', '3' => '3 Walls', '4' => '4 Walls'], '4', $walls),
        ];
        for ($i = 1; $i <= 4; $i++) {
            $show = ['mode' => 'walls', 'num_walls' => array_map('strval', range($i, 4))];
            $inputs[] = self::dim("wall{$i}_length", "Wall $i Length", ['rule' => 'nonneg', 'show_if' => $show]);
            $inputs[] = self::dim("wall{$i}_height", "Wall $i Height", ['rule' => 'nonneg', 'show_if' => $show]);
        }
        $opt = ['required' => false, 'rule' => 'nonneg'];
        return self::calc('paint_area', 'Paint Area Calculator', 'Find the net wall (and ceiling) area to paint, after doors and windows.', array_merge($inputs, [
            self::dim('door_width', 'Door Width', $opt),
            self::dim('door_height', 'Door Height', $opt),
            self::num('door_count', 'Number of Doors', null, $opt + ['default' => '0', 'integer' => true]),
            self::dim('window_width', 'Window Width', $opt),
            self::dim('window_height', 'Window Height', $opt),
            self::num('window_count', 'Number of Windows', null, $opt + ['default' => '0', 'integer' => true]),
        ]));
    }

    private static function runPaintArea(array $n, array $v): array
    {
        $sqm = $v['unit'] === 'm';
        $toFt = fn(float $a): float => $sqm ? $a * 10.764 : $a;
        $fromFt = fn(float $a): float => $sqm ? $a / 10.764 : $a;
        $label = $sqm ? 'm²' : 'sq ft';

        // Deductions in ft²; dimensions are required when a count is given (fix, deviation 7).
        $errors = [];
        $deduct = function (string $kind) use ($n, $sqm, &$errors): float {
            $count = (float) ($n[$kind . '_count'] ?? 0);
            if ($count <= 0) {
                return 0.0;
            }
            foreach (['width', 'height'] as $d) {
                if (empty($n[$kind . '_' . $d])) {
                    $errors[$kind . '_' . $d] = 'Required when the number of ' . $kind . 's is above 0.';
                }
            }
            $f = $sqm ? 3.281 : 1.0;
            return ((float) $n[$kind . '_width'] * $f) * ((float) $n[$kind . '_height'] * $f) * $count;
        };
        $door = $deduct('door');
        $window = $deduct('window');
        if ($errors) {
            return ['errors' => $errors];
        }

        $wallRows = [];
        $ceilingIncluded = $v['include_ceiling'] === 'yes';
        if ($v['mode'] === 'carpet') {
            $c = $toFt((float) $n['carpet_area']);
            $wall = $c * 3.5;
            $ceiling = 0.0;
            if ($ceilingIncluded) {
                $ceiling = $c;
                $wall = $c * 2.5;
            }
            $net = $wall + $ceiling - $door - $window;
            $wallShown = $fromFt($wall);
            $ceilingShown = $fromFt($ceiling);
        } else {
            $total = 0.0;
            for ($i = 1, $count = (int) $v['num_walls']; $i <= $count; $i++) {
                $a = $n["wall{$i}_length"] * $n["wall{$i}_height"];
                $total += $a;
                $wallRows[] = self::row("Wall $i", self::fmt($a), $label);
            }
            $ceiling = $ceilingIncluded && $count >= 2 ? $n['wall1_length'] * $n['wall2_length'] : 0.0;
            $net = $toFt($total) + $toFt($ceiling) - $door - $window;
            $wallShown = $total;
            $ceilingShown = $ceiling;
        }

        $rows = [self::row('Wall Area', self::fmt($wallShown), $label)];
        if ($ceilingIncluded && $ceilingShown > 0) {
            $rows[] = self::row('Ceiling Area', self::fmt($ceilingShown), $label);
        }
        if ($door > 0) {
            $rows[] = self::row('Door Area (−)', self::fmt($fromFt($door)), $label);
        }
        if ($window > 0) {
            $rows[] = self::row('Window Area (−)', self::fmt($fromFt($window)), $label);
        }
        $rows[] = self::row('Net Paint Area', self::fmt($fromFt($net)), $label);
        return [
            'rows' => array_merge($rows, $wallRows),
            'note' => $v['mode'] === 'carpet'
                ? 'Wall area = carpet area × 3.5 (× 2.5 plus the ceiling when the ceiling is included).'
                : 'Ceiling = Wall 1 length × Wall 2 length.',
        ];
    }

    // ---- Excavation ----------------------------------------------------
    private static function defExcavation(): array
    {
        return self::calc('excavation', 'Excavation Calculator', 'Calculate the volume of earth to dig out for a pit or trench.', [
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width'),
            self::dim('depth', 'Depth'),
        ]);
    }

    private static function runExcavation(array $n, array $v): array
    {
        $vol = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']) * self::m($n['depth'], $v['unit']);
        return [
            'rows' => [
                self::row('Volume', self::fmt($vol), 'm³'),
                self::row('Volume', self::fmt($vol * 35.3147), 'ft³'),
            ],
            'note' => 'Volume = Length × Width × Depth. 1 m³ = 35.3147 ft³.',
        ];
    }

    // ---- Top soil ------------------------------------------------------
    private static function defTopSoil(): array
    {
        $cost = ['cost_enabled' => 'yes'];
        return self::calc('top_soil', 'Top Soil Calculator', 'Volume, weight and cost of top soil for a garden bed or lawn.', [
            self::radio('unit', 'Unit of Measurement', self::units(['m', 'cm', 'ft', 'in']), 'm'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width'),
            self::dim('depth', 'Depth'),
            self::select('density', 'Soil Density', ['1600' => 'Standard (1600 kg/m³)', '1200' => 'Loose (1200 kg/m³)', 'custom' => 'Custom'], '1600'),
            self::num('custom_density', 'Custom Density', 'kg/m³', ['required' => false, 'fallback' => 1600.0, 'show_if' => ['density' => 'custom']]),
            self::num('extra_pct', 'Extra Material', '%', ['default' => '5', 'rule' => 'nonneg', 'max' => 15, 'step' => '1']),
            self::yesNo('cost_enabled', 'Cost Calculation', 'no'),
            self::radio('pricing_mode', 'Pricing', ['bulk' => 'Bulk', 'bagged' => 'Bagged'], 'bulk', $cost),
            self::num('price_per_ton', 'Price per Ton', null, ['required' => false, 'rule' => 'nonneg', 'show_if' => $cost + ['pricing_mode' => 'bulk']]),
            self::num('bag_size', 'Bag Size', 'kg', ['required' => false, 'show_if' => $cost + ['pricing_mode' => 'bagged']]),
            self::num('price_per_bag', 'Price per Bag', null, ['required' => false, 'rule' => 'nonneg', 'show_if' => $cost + ['pricing_mode' => 'bagged']]),
        ], true);
    }

    private static function runTopSoil(array $n, array $v, string $cur): array
    {
        $vol = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']) * self::m($n['depth'], $v['unit']);
        $density = $v['density'] === 'custom' ? (float) ($n['custom_density'] ?? 1600.0) : (float) $v['density'];
        $weightKg = $vol * $density;
        $extra = (float) $n['extra_pct'];
        $recommended = $vol * (1 + $extra / 100);
        $rows = [
            self::row('Exact Volume', self::fmt($vol), 'm³'),
            self::row('Volume', self::fmt($vol * 35.3147), 'ft³'),
            self::row('Recommended Volume', self::fmt($recommended), 'm³'),
            self::row('Weight', self::fmt($weightKg), 'kg'),
            self::row('Weight', self::fmt($weightKg / 1000), 'tons'),
        ];
        if ($v['cost_enabled'] === 'yes') {
            if ($v['pricing_mode'] === 'bulk' && $n['price_per_ton'] !== null) {
                $rows[] = self::row('Total Estimated Cost', self::money(($recommended * $density / 1000) * $n['price_per_ton'], $cur));
            } elseif ($v['pricing_mode'] === 'bagged' && $n['bag_size'] !== null) {
                $bags = $recommended * $density / $n['bag_size'];
                $rows[] = self::row('Number of Bags', self::fmt(ceil($bags), 0), 'bags');
                if ($n['price_per_bag'] !== null) {
                    $rows[] = self::row('Total Estimated Cost', self::money($bags * $n['price_per_bag'], $cur));
                }
            }
        }
        return [
            'rows' => $rows,
            'note' => 'Recommended volume includes ' . self::plain($extra) . '% extra material. Weight uses the exact volume at a density of ' . self::fmt($density, 0) . ' kg/m³.',
        ];
    }

    // ---- Blue metal ----------------------------------------------------
    private static function defBlueMetal(): array
    {
        return self::calc('blue_metal', 'Blue Metal Calculator', 'Volume and weight of blue metal (crushed stone aggregate).', [
            self::radio('size', 'Aggregate Size', ['10mm' => '10mm, fine aggregate', '20mm' => '20mm, medium aggregate', '40mm' => '40mm, coarse aggregate'], '20mm'),
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width'),
            self::dim('depth', 'Depth / Thickness'),
        ]);
    }

    private static function runBlueMetal(array $n, array $v): array
    {
        $density = ['10mm' => 1400, '20mm' => 1350, '40mm' => 1300][$v['size']];
        $vol = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']) * self::m($n['depth'], $v['unit']);
        $kg = $vol * $density;
        return [
            'rows' => [
                self::row('Volume', self::fmt($vol), 'm³'),
                self::row('Volume', self::fmt($vol * 35.3147), 'ft³'),
                self::row('Weight', self::fmt($kg), 'kg'),
                self::row('Weight', self::fmt($kg / 1000), 'tons'),
                self::row('Density Used', self::fmt($density, 0), 'kg/m³'),
            ],
            'note' => 'Weight = Volume × Density (' . $v['size'] . ' aggregate: ' . $density . ' kg/m³).',
        ];
    }

    // ---- Anti termite --------------------------------------------------
    private static function defAntiTermite(): array
    {
        return self::calc('anti_termite', 'Anti-Termite Calculator', 'Chemical needed for anti-termite treatment of a floor area.', [
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width'),
        ]);
    }

    private static function runAntiTermite(array $n, array $v): array
    {
        $area = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']);
        $ml = $area * 30.0;
        return [
            'rows' => [
                self::row('Area', self::fmt($area), 'm²'),
                self::row('Area', self::fmt($area * 10.7639), 'ft²'),
                self::row('Dosage Rate', '30', 'ml/m²'),
                self::row('Chemical', self::fmt($ml), 'ml'),
                self::row('Chemical', self::fmt($ml / 1000), 'liters'),
            ],
            'note' => 'Dosage of 30 ml per m² (as per IS 6313-2).',
        ];
    }

    // ---- Wood frame ----------------------------------------------------
    private static function defWoodFrame(): array
    {
        return self::calc('wood_frame', 'Wood Frame Calculator', 'Volume of timber for door or window frames.', [
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft', 'in']), 'm'),
            self::dim('length', 'Length'),
            self::dim('depth', 'Depth / Height'),
            self::dim('thickness', 'Thickness / Width'),
            self::num('quantity', 'Quantity', 'pieces', ['default' => '1', 'integer' => true]),
        ]);
    }

    private static function runWoodFrame(array $n, array $v): array
    {
        $m3 = self::m($n['length'], $v['unit']) * self::m($n['depth'], $v['unit']) * self::m($n['thickness'], $v['unit']);
        $ft3 = $m3 * 35.3147;
        $q = $n['quantity'];
        return [
            'rows' => [
                self::row('Total Volume', self::fmt($m3 * $q), 'm³'),
                self::row('Total Volume', self::fmt($ft3 * $q), 'ft³'),
                self::row('Per Frame', self::fmt($m3), 'm³'),
                self::row('Per Frame', self::fmt($ft3), 'ft³'),
                self::row('Quantity', self::fmt($q, 0), 'pieces'),
            ],
            'note' => '1 m³ = 35.3147 ft³.',
        ];
    }

    // ---- Plywood sheets ------------------------------------------------
    private static function defPlywoodSheets(): array
    {
        $custom = ['preset' => 'custom'];
        return self::calc('plywood_sheets', 'Plywood Sheets Calculator', 'Number of plywood sheets needed to cover a floor, wall or ceiling.', [
            self::radio('type', 'Plywood Type', ['Interior' => 'Interior', 'Exterior' => 'Exterior'], 'Interior'),
            self::radio('unit', 'Measurement Unit', self::units(['ft', 'm', 'cm', 'in']), 'ft'),
            self::dim('room_length', 'Room Length'),
            self::dim('room_width', 'Room Width'),
            self::select('preset', 'Plywood Size', ['4x8' => '4 × 8 ft', '4x10' => '4 × 10 ft', '4x12' => '4 × 12 ft', '5x5' => '5 × 5 ft', 'custom' => 'Custom size'], '4x8'),
            self::dim('ply_length', 'Plywood Length', ['show_if' => $custom]),
            self::dim('ply_width', 'Plywood Width', ['show_if' => $custom]),
        ]);
    }

    private static function runPlywoodSheets(array $n, array $v): array
    {
        $toFt = ['ft' => 1.0, 'm' => 3.28084, 'cm' => 0.0328084, 'in' => 1 / 12][$v['unit']];
        $room = ($n['room_length'] * $toFt) * ($n['room_width'] * $toFt);
        if ($v['preset'] === 'custom') {
            $cover = ($n['ply_length'] * $toFt) * ($n['ply_width'] * $toFt);
        } else {
            [$w, $l] = array_map('floatval', explode('x', $v['preset']));   // presets are in feet
            $cover = $l * $w;
        }
        $exact = $room / $cover;
        $sheets = ceil($exact);
        $waste = (($sheets * $cover - $room) / $room) * 100;
        return [
            'rows' => [
                self::row('Sheets Needed', self::fmt($sheets, 0), 'sheets'),
                self::row('Waste', self::fmt($waste), '%'),
                self::row('Room Area', self::fmt($room), 'sq ft'),
                self::row('Plywood Coverage', self::fmt($cover), 'sq ft per sheet'),
                self::row('Exact Sheets', self::fmt($exact), 'sheets'),
            ],
            'note' => 'Type: ' . $v['type'] . '. Sheets are rounded up to whole sheets.',
        ];
    }

    // ---- Stair case ----------------------------------------------------
    private static function defStairCase(): array
    {
        $landing = ['include_landing' => 'yes'];
        return self::calc('stair_case', 'Stair Case Calculator', 'Concrete volume and materials for a staircase with waist slab.', [
            self::radio('unit', 'Unit of Measurement', self::units(['m', 'cm', 'ft', 'in']), 'm'),
            self::select('grade', 'Concrete Grade', self::gradeOptions(), 'M20'),
            self::num('steps', 'Number of Steps', 'steps', ['integer' => true]),
            self::dim('riser', 'Riser Height'),
            self::dim('tread', 'Tread Width'),
            self::dim('step_width', 'Step Width (Length)'),
            self::dim('waist', 'Waist Slab Thickness'),
            self::yesNo('include_landing', 'Include Landing', 'no'),
            self::dim('landing_length', 'Landing Length', ['show_if' => $landing]),
            self::dim('landing_width', 'Landing Width', ['show_if' => $landing]),
            self::dim('landing_thickness', 'Landing Thickness', ['show_if' => $landing]),
        ]);
    }

    private static function runStairCase(array $n, array $v): array
    {
        $u = $v['unit'];
        [$riser, $tread, $width, $waist] = [self::m($n['riser'], $u), self::m($n['tread'], $u), self::m($n['step_width'], $u), self::m($n['waist'], $u)];
        $steps = $n['steps'];
        $stepsVol = 0.5 * $riser * $tread * $width * $steps;
        $inclined = sqrt(($tread * $steps) ** 2 + ($riser * $steps) ** 2);
        $waistVol = $inclined * $width * $waist;
        $landingVol = $v['include_landing'] === 'yes'
            ? self::m($n['landing_length'], $u) * self::m($n['landing_width'], $u) * self::m($n['landing_thickness'], $u)
            : 0.0;
        $wet = $stepsVol + $waistVol + $landingVol;
        $dry = $wet * 1.54;
        $rows = [
            self::row('Steps Volume', self::fmt($stepsVol), 'm³'),
            self::row('Waist Slab Volume', self::fmt($waistVol), 'm³'),
        ];
        if ($landingVol > 0) {
            $rows[] = self::row('Landing Volume', self::fmt($landingVol), 'm³');
        }
        $rows[] = self::row('Total Wet Volume', self::fmt($wet), 'm³');
        $rows[] = self::row('Total Dry Volume', self::fmt($dry), 'm³');
        return [
            'rows' => array_merge($rows, self::concreteMaterials($dry, $v['grade'])),
            'note' => 'Grade ' . $v['grade'] . ' (' . self::GRADE_RATIOS[$v['grade']] . '). Dry volume = wet volume × 1.54. 1 cement bag = 50 kg = 0.035 m³.',
        ];
    }

    // ---- Round column --------------------------------------------------
    private static function defRoundColumn(): array
    {
        return self::calc('round_column', 'Round Column Calculator', 'Concrete and materials for circular columns.', [
            self::radio('unit', 'Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::dim('diameter', 'Diameter'),
            self::dim('height', 'Height'),
            self::num('columns', 'Number of Columns', 'columns', ['default' => '1', 'integer' => true]),
            self::select('grade', 'Concrete Grade', self::gradeOptions(), 'M20'),
        ]);
    }

    private static function runRoundColumn(array $n, array $v): array
    {
        $r = self::m($n['diameter'], $v['unit']) / 2;
        $volume = M_PI * $r * $r * self::m($n['height'], $v['unit']) * $n['columns'];
        $dry = $volume * 1.524;
        return [
            'rows' => array_merge([
                self::row('Volume', self::fmt($volume), 'm³'),
                self::row('Dry Volume', self::fmt($dry), 'm³'),
            ], self::concreteMaterials($dry, $v['grade'])),
            'note' => 'Volume = π × r² × h × No. of Columns. Dry volume = volume × 1.524.',
        ];
    }

    // ---- Concrete tube -------------------------------------------------
    private static function defConcreteTube(): array
    {
        return self::calc('concrete_tube', 'Concrete Tube Calculator', 'Concrete and materials for hollow tubes, pipes or rings.', [
            self::radio('mode', 'Input Mode', ['diameter' => 'Inner Diameter', 'thickness' => 'Wall Thickness'], 'diameter'),
            self::select('unit', 'Unit of Measurement', self::units(['m', 'cm', 'mm', 'ft', 'in']), 'm'),
            self::select('grade', 'Concrete Grade', self::gradeOptions(), 'M20'),
            self::dim('outer_d', 'Outer Diameter'),
            self::dim('inner_d', 'Inner Diameter', ['show_if' => ['mode' => 'diameter']]),
            self::dim('wall_t', 'Wall Thickness', ['show_if' => ['mode' => 'thickness']]),
            self::dim('height', 'Height / Length'),
            self::num('tubes', 'Number of Tubes', 'tubes', ['default' => '1', 'integer' => true]),
        ]);
    }

    private static function runConcreteTube(array $n, array $v): array
    {
        $inner = $v['mode'] === 'diameter' ? $n['inner_d'] : $n['outer_d'] - 2 * $n['wall_t'];
        $outerM = self::m($n['outer_d'], $v['unit']);
        $innerM = self::m($inner, $v['unit']);
        if ($innerM >= $outerM) {
            return ['errors' => ['inner_d' => 'Inner diameter must be smaller than outer diameter.']];
        }
        if ($innerM < 0) {
            return ['errors' => ['wall_t' => 'Wall thickness must be less than half the outer diameter.']];
        }
        [$ro, $ri] = [$outerM / 2, $innerM / 2];
        $net = M_PI * $ro * $ro - M_PI * $ri * $ri;
        $volume = $net * self::m($n['height'], $v['unit']) * $n['tubes'];
        $dry = $volume * 1.524;
        return [
            'rows' => array_merge([
                self::row('Outer Radius', self::fmt($ro), 'm'),
                self::row('Inner Radius', self::fmt($ri), 'm'),
                self::row('Net Cross-Sectional Area', self::fmt($net), 'm²'),
                self::row('Wet Volume', self::fmt($volume), 'm³'),
                self::row('Dry Volume', self::fmt($dry), 'm³'),
            ], self::concreteMaterials($dry, $v['grade'])),
            'note' => 'Area = π × (Ro² − Ri²). Dry volume = wet volume × 1.524.',
        ];
    }

    // ---- Construction cost ---------------------------------------------
    private static function defConstructionCost(): array
    {
        $regions = [];
        foreach (self::CC_PRICES as $code => [$name, $currency]) {
            $regions[$code] = $name . ' (' . $currency . ')';
        }
        $inputs = [
            self::select('region', 'Pricing Region', $regions, 'US'),
            self::num('area', 'Built-up Area', 'sq ft'),
        ];
        // One editable price set per region, prefilled from the default tables.
        foreach (self::CC_PRICES as $code => [, , $prices]) {
            foreach (self::CC_MATERIALS as $key => [, , $label, $per]) {
                $inputs[] = self::num('price_' . strtolower($code) . '_' . $key, $label . ' Price', 'per ' . $per, [
                    'default' => self::plain($prices[$key]), 'required' => false, 'rule' => 'nonneg',
                    'show_if' => ['region' => $code],
                ]);
            }
        }
        return self::calc('construction_cost', 'Construction Cost Estimator', 'Approximate material cost of a building from its built-up area.', $inputs, true);
    }

    private static function runConstructionCost(array $n, array $v, string $cur): array
    {
        $area = $n['area'];
        $region = $v['region'];
        $defaults = self::CC_PRICES[$region][2];
        $lines = [];
        $total = 0.0;
        foreach (self::CC_MATERIALS as $key => [$perSqft, $qtyUnit, $label, $per]) {
            $qty = $area * $perSqft;
            $price = $n['price_' . strtolower($region) . '_' . $key] ?? $defaults[$key];
            // Price units: steel per tonne (qty in kg), wood per m³ (qty in cft); see deviation 11.
            $billable = match ($key) {
                'steel' => $qty / 1000,
                'wood' => $qty * 0.0283168,
                default => $qty,
            };
            $cost = $billable * $price;
            $total += $cost;
            $lines[] = [$label, $qty, $qtyUnit, $price, $per, $cost];
        }
        $rows = [
            self::row('Total Construction Cost', self::money($total, $cur)),
            self::row('Cost per sq ft', self::money($total / $area, $cur), 'per sq ft'),
        ];
        foreach ($lines as [$label, $qty, $qtyUnit, $price, $per, $cost]) {
            $pct = $total > 0 ? $cost / $total * 100 : 0.0;
            $rows[] = self::row(
                $label . ': ' . self::fmt($qty, 1) . ' ' . $qtyUnit . ' × ' . self::money($price, $cur) . '/' . $per,
                self::money($cost, $cur),
                self::fmt($pct, 1) . '%'
            );
        }
        return [
            'rows' => $rows,
            'note' => 'Prices are typical ' . self::CC_PRICES[$region][0] . ' market rates. Edit them to match your local quotes. Quantities use thumb rules per sq ft of built-up area.',
        ];
    }

    // ---- Labor cost ----------------------------------------------------
    private static function defLaborCost(): array
    {
        $trades = [];
        foreach (self::TRADES as $key => [$name, $base]) {
            $trades[$key] = $name . ' ($' . self::fmt($base) . '/hr)';
        }
        return self::calc('labor_cost', 'Labor Cost Calculator', 'Total labor cost with overtime and burden (taxes, insurance, overhead).', [
            self::radio('mode', 'Rate', ['custom' => 'Custom Rate', 'trade' => 'Trade Category'], 'custom'),
            self::select('trade', 'Select Trade', $trades, 'general_laborer', ['mode' => 'trade']),
            self::num('hourly_rate', 'Hourly Rate', 'per hr', ['rule' => 'nonneg', 'show_if' => ['mode' => 'custom']]),
            self::num('hours', 'Regular Hours', 'hrs', ['rule' => 'nonneg']),
            self::num('workers', 'Number of Workers', 'workers', ['default' => '1', 'rule' => 'nonneg', 'integer' => true]),
            self::num('overtime_hours', 'Overtime Hours', 'hrs', ['default' => '0', 'rule' => 'nonneg']),
            self::yesNo('include_burden', 'Include Burden / Overhead', 'yes'),
            self::num('burden_pct', 'Burden Percentage', '%', ['default' => '35', 'rule' => 'nonneg', 'show_if' => ['include_burden' => 'yes'], 'hint' => 'Taxes, insurance, benefits, overhead (~25–50% of base wages)']),
        ], true);
    }

    private static function runLaborCost(array $n, array $v, string $cur): array
    {
        $rate = $v['mode'] === 'trade' ? self::TRADES[$v['trade']][1] : $n['hourly_rate'];
        $basic = $rate * $n['hours'] * $n['workers'];
        $overtime = $rate * 1.5 * $n['overtime_hours'] * $n['workers'];
        $burden = ($basic + $overtime) * ($v['include_burden'] === 'yes' ? $n['burden_pct'] : 0) / 100;
        $total = $basic + $overtime + $burden;
        $hours = ($n['hours'] + $n['overtime_hours']) * $n['workers'];
        $rows = [
            self::row('Total Labor Cost', self::money($total, $cur)),
            self::row('Effective Rate', self::money($hours > 0 ? $total / $hours : 0.0, $cur), 'per hr'),
            self::row('Regular Labor', self::money($basic, $cur)),
        ];
        if ($overtime > 0) {
            $rows[] = self::row('Overtime (1.5×)', self::money($overtime, $cur));
        }
        if ($burden > 0) {
            $rows[] = self::row('Burden / Overhead', self::money($burden, $cur));
        }
        return [
            'rows' => $rows,
            'note' => $v['mode'] === 'trade' ? 'Trade rates are typical US base wages in USD.' : 'Overtime is paid at 1.5× the hourly rate.',
        ];
    }

    // ---- Hardware & fittings -------------------------------------------
    private static function defHardware(): array
    {
        return self::calc('hardware', 'Hardware & Fittings Calculator', 'Budget for windows, doors, plumbing, electrical and sanitary fittings.', [
            self::radio('mode', 'Calculate From', ['total' => 'Total Cost', 'area' => 'From Area'], 'total'),
            self::num('total_cost', 'Total Construction Cost', null, ['show_if' => ['mode' => 'total']]),
            self::num('area', 'Built-up Area', 'sq ft', ['show_if' => ['mode' => 'area']]),
            self::num('cost_per_sqft', 'Cost per Square Foot', 'per sq ft', ['show_if' => ['mode' => 'area']]),
        ], true);
    }

    private static function runHardware(array $n, array $v, string $cur): array
    {
        $total = $v['mode'] === 'total' ? $n['total_cost'] : $n['area'] * $n['cost_per_sqft'];
        $rows = [self::row('Total Fittings (22.8%)', self::money($total * 0.228, $cur))];
        foreach (self::HARDWARE_PCT as $item => $pct) {
            $rows[] = self::row($item . ' (' . self::fmt($pct, 1) . '%)', self::money($total * $pct / 100, $cur));
        }
        return [
            'rows' => $rows,
            'note' => 'Fittings are estimated at 22.8% of the total construction cost of ' . self::money($total, $cur) . '.',
        ];
    }

    // ---- Carpet area ---------------------------------------------------
    private const ROOMS = ['bedroom' => 'Bedrooms', 'living' => 'Living Room', 'kitchen' => 'Kitchen', 'bathroom' => 'Bathrooms', 'balcony' => 'Balcony', 'dining' => 'Dining Room', 'study' => 'Study Room'];

    private static function defCarpetArea(): array
    {
        $inputs = [
            self::radio('mode', 'Calculation Mode', ['rooms' => 'From Rooms', 'builtup' => 'From Built-Up'], 'rooms'),
            self::radio('unit', 'Unit of Measurement', ['sqm' => 'Sq. Meters', 'sqft' => 'Sq. Feet'], 'sqm'),
        ];
        foreach (self::ROOMS as $key => $label) {
            $inputs[] = self::num($key, $label, null, ['unit_from' => 'unit', 'required' => false, 'rule' => 'nonneg', 'show_if' => ['mode' => 'rooms']]);
        }
        $inputs[] = self::num('built_up', 'Built-Up Area', null, ['unit_from' => 'unit', 'show_if' => ['mode' => 'builtup']]);
        $inputs[] = self::num('built_up_pct', 'Built-up Area (% over carpet)', '%', ['default' => '20', 'rule' => 'nonneg', 'min' => 10, 'max' => 30, 'step' => '1']);
        $inputs[] = self::num('common_pct', 'Common Area (% over built-up)', '%', ['default' => '15', 'rule' => 'nonneg', 'max' => 30, 'step' => '1']);
        return self::calc('carpet_area', 'Carpet Area Calculator', 'Convert between carpet, built-up and super built-up area.', $inputs);
    }

    private static function runCarpetArea(array $n, array $v): array
    {
        $u = $v['unit'] === 'sqm' ? 'm²' : 'sq ft';
        $bu = 1 + $n['built_up_pct'] / 100;
        $cm = 1 + $n['common_pct'] / 100;
        $roomRows = [];
        if ($v['mode'] === 'rooms') {
            $carpet = 0.0;
            foreach (self::ROOMS as $key => $label) {
                $a = (float) ($n[$key] ?? 0);
                $carpet += $a;
                if ($a > 0) {
                    $roomRows[] = self::row($label, self::fmt($a), $u);
                }
            }
            $builtUp = $carpet * $bu;
        } else {
            $builtUp = $n['built_up'];
            $carpet = $builtUp / $bu;
        }
        return [
            'rows' => array_merge([
                self::row('Carpet Area (usable floor area)', self::fmt($carpet), $u),
                self::row('Built-Up Area (carpet + walls)', self::fmt($builtUp), $u),
                self::row('Super Built-Up Area (built-up + common)', self::fmt($builtUp * $cm), $u),
            ], $roomRows),
            'note' => 'Under RERA, carpet area is the net usable floor area, excluding external walls, service shafts, balconies and common areas.',
        ];
    }

    // ---- Roof area -----------------------------------------------------
    private static function defRoofArea(): array
    {
        $pitched = ['roof_type' => ['gable', 'hip']];
        return self::calc('roof_area', 'Roof Area Calculator', 'Sloped roof surface area from the footprint and pitch, with waste and cost.', [
            self::radio('unit', 'Unit of Measurement', self::units(['m', 'ft', 'cm']), 'm'),
            self::radio('roof_type', 'Roof Type', ['flat' => 'Flat', 'gable' => 'Gable', 'hip' => 'Hip'], 'gable'),
            self::dim('length', 'Length'),
            self::dim('width', 'Width'),
            self::radio('pitch_mode', 'Roof Pitch', ['angle' => 'Angle', 'ratio' => 'Ratio', 'percentage' => 'Percentage'], 'angle', $pitched),
            self::num('pitch_angle', 'Pitch Angle', 'degrees', ['max' => 89.99, 'show_if' => $pitched + ['pitch_mode' => 'angle']]),
            self::num('rise', 'Rise', 'in', ['show_if' => $pitched + ['pitch_mode' => 'ratio']]),
            self::num('run', 'Run', 'in', ['default' => '12', 'show_if' => $pitched + ['pitch_mode' => 'ratio']]),
            self::num('slope_pct', 'Slope Percentage', '%', ['show_if' => $pitched + ['pitch_mode' => 'percentage']]),
            self::num('waste_pct', 'Waste Percentage', '%', ['default' => '10', 'rule' => 'nonneg', 'max' => 20, 'step' => '1']),
            self::yesNo('cost_enabled', 'Cost Calculation', 'no'),
            self::num('price_per_m2', 'Price per m²', null, ['required' => false, 'rule' => 'nonneg', 'show_if' => ['cost_enabled' => 'yes']]),
        ], true);
    }

    private static function runRoofArea(array $n, array $v, string $cur): array
    {
        $base = self::m($n['length'], $v['unit']) * self::m($n['width'], $v['unit']);
        $rows = [];
        if ($v['roof_type'] === 'flat') {
            $total = $base;
            $rows[] = self::row('Base Area', self::fmt($base), 'm²');
        } else {
            $rad = match ($v['pitch_mode']) {
                'angle' => $n['pitch_angle'] * M_PI / 180,
                'ratio' => atan($n['rise'] / $n['run']),
                default => atan($n['slope_pct'] / 100),
            };
            $pf = 1 / cos($rad);
            $single = $base * $pf;
            $total = $single * ($v['roof_type'] === 'hip' ? 2.2 : 2.0);
            $rows[] = self::row('Pitch Angle', self::fmt($rad * 180 / M_PI), '°');
            $rows[] = self::row('Pitch Factor', self::fmt($pf));
            $rows[] = self::row('Base Area', self::fmt($base), 'm²');
            $rows[] = self::row('Single Side Area', self::fmt($single), 'm²');
        }
        $waste = $n['waste_pct'];
        $withWaste = $total * (1 + $waste / 100);
        $rows[] = self::row('Total Roof Area', self::fmt($total), 'm²');
        $rows[] = self::row('Area with Waste', self::fmt($withWaste), 'm²');
        if ($v['cost_enabled'] === 'yes' && $n['price_per_m2'] !== null) {
            $rows[] = self::row('Total Estimated Cost', self::money($withWaste * $n['price_per_m2'], $cur));
        }
        return ['rows' => $rows, 'note' => 'Includes ' . self::plain($waste) . '% waste allowance.'];
    }

    // ==================================================================
    // METAL CALCULATORS
    // ==================================================================

    // ---- Steel bar (thumb rule) ----------------------------------------
    private static function defSteelBar(): array
    {
        return self::calc('steel_bar', 'Steel Bar Calculator', 'Thumb-rule reinforcement steel for a building from its built-up area.', [
            self::radio('unit', 'Area Unit', ['sqft' => 'Square Feet', 'sqm' => 'Square Meters'], 'sqft'),
            self::num('area', 'Built-Up Area', null, ['unit_from' => 'unit']),
        ]);
    }

    private static function runSteelBar(array $n, array $v): array
    {
        $area = $n['area'] * ($v['unit'] === 'sqm' ? 10.7639 : 1.0);
        $kg = $area * 4.0;
        return [
            'rows' => [
                self::row('Steel Required', self::fmt($kg), 'kg'),
                self::row('Steel Required', self::fmt($kg / 1000), 'tons'),
                self::row('Built-Up Area', self::fmt($area), 'sq ft'),
            ],
            'note' => 'Thumb rule: 4 kg of steel per sq ft of built-up area (medium-spec residential). Actual quantities depend on the structural drawings.',
        ];
    }

    // ---- Steel quantity ------------------------------------------------
    private static function defSteelQuantity(): array
    {
        $inputs = [self::radio('mode', 'Calculation Mode', ['member' => 'By Member Type', 'area' => 'From Built-up Area'], 'member')];
        foreach (self::STEEL_MEMBERS as $key => [$label, $ratio]) {
            $inputs[] = self::num($key, $label . ' (' . $ratio . ' kg/m³)', 'm³', ['required' => false, 'rule' => 'nonneg', 'show_if' => ['mode' => 'member']]);
        }
        $inputs[] = self::radio('unit', 'Unit of Measurement', ['sqm' => 'Square Meters', 'sqft' => 'Square Feet'], 'sqm', ['mode' => 'area']);
        $inputs[] = self::num('built_up_area', 'Built-up Area', null, ['unit_from' => 'unit', 'show_if' => ['mode' => 'area'], 'hint' => 'Approximate: 43 kg of steel per m²']);
        $inputs[] = self::num('wastage', 'Wastage Allowance', '%', ['default' => '5', 'rule' => 'nonneg', 'max' => 10, 'step' => '0.5', 'hint' => 'Extra for cutting, bending and wastage (typical 3–6%)']);
        return self::calc('steel_quantity', 'Steel Quantity Calculator', 'Reinforcement steel for RCC members from concrete volumes or built-up area.', $inputs);
    }

    private static function runSteelQuantity(array $n, array $v): array
    {
        $breakdown = [];
        $total = 0.0;
        if ($v['mode'] === 'member') {
            foreach (self::STEEL_MEMBERS as $key => [$label, $ratio]) {
                $vol = (float) ($n[$key] ?? 0);
                if ($vol > 0) {
                    $steel = $vol * $ratio;
                    $total += $steel;
                    $breakdown[] = self::row($label . ' (' . $ratio . ' kg/m³)', self::fmt($steel), 'kg');
                }
            }
        } else {
            $area = $n['built_up_area'] * ($v['unit'] === 'sqft' ? 0.092903 : 1.0);
            $total = $area * 43;
        }
        $withWastage = $total * (1 + $n['wastage'] / 100);
        return [
            'rows' => array_merge([
                self::row('Base Steel Quantity', self::fmt($total), 'kg'),
                self::row('Steel with Wastage', self::fmt($withWastage), 'kg'),
                self::row('Total', self::fmt($withWastage / 1000), 'tons'),
            ], count($breakdown) > 1 ? $breakdown : []),
            'note' => 'Includes ' . self::plain($n['wastage']) . '% wastage allowance. Steel = concrete volume × steel ratio.',
        ];
    }

    // ---- Steel weight --------------------------------------------------
    private static function defSteelWeight(): array
    {
        $u = ['unit_from' => 'unit'];
        return self::calc('steel_weight', 'Steel Weight Calculator', 'Weight of round, square, rectangular, hexagonal or flat steel bars.', [
            self::select('shape', 'Steel Shape', self::opts(['Round Bar', 'Square Bar', 'Rectangular Bar', 'Hexagonal Bar', 'Flat Bar']), 'Round Bar'),
            self::select('unit', 'Unit of Measurement', self::units(['mm', 'cm', 'm', 'ft', 'in']), 'mm'),
            self::num('diameter', 'Diameter', null, $u + ['show_if' => ['shape' => 'Round Bar']]),
            self::num('side', 'Side Width', null, $u + ['show_if' => ['shape' => 'Square Bar']]),
            self::num('width', 'Width', null, $u + ['show_if' => ['shape' => ['Rectangular Bar', 'Flat Bar']]]),
            self::num('height', 'Height', null, $u + ['show_if' => ['shape' => 'Rectangular Bar']]),
            self::num('thickness', 'Thickness', null, $u + ['show_if' => ['shape' => 'Flat Bar']]),
            self::num('across_flats', 'Width (flat to flat)', null, $u + ['show_if' => ['shape' => 'Hexagonal Bar']]),
            self::num('length', 'Length', null, $u),
            self::num('quantity', 'Quantity', 'pieces', ['default' => '1', 'integer' => true]),
        ]);
    }

    private static function runSteelWeight(array $n, array $v): array
    {
        $u = $v['unit'];
        $len = self::m($n['length'], $u);
        $area = match ($v['shape']) {
            'Round Bar' => M_PI * (self::m($n['diameter'], $u) / 2) ** 2,
            'Square Bar' => self::m($n['side'], $u) ** 2,
            'Rectangular Bar' => self::m($n['width'], $u) * self::m($n['height'], $u),
            'Flat Bar' => self::m($n['width'], $u) * self::m($n['thickness'], $u),
            default => (3 * sqrt(3) / 2) * (self::m($n['across_flats'], $u) / sqrt(3)) ** 2,   // hexagon
        };
        $volUnit = $area * $len;
        $q = $n['quantity'];
        $wUnit = $volUnit * 7850;
        $wTotal = $volUnit * $q * 7850;
        $perM = $wUnit / $len;
        return [
            'rows' => [
                self::row('Weight per Unit', self::fmt($wUnit), 'kg'),
                self::row('Total Weight', self::fmt($wTotal), 'kg'),
                self::row('Total Weight', self::fmt($wTotal / 1000), 'tons'),
                self::row('Volume per Unit', self::fmt($volUnit * 1000000), 'cm³'),
                self::row('Total Volume', self::fmt($volUnit * $q * 1000000), 'cm³'),
                self::row('Weight per Meter', self::fmt($perM), 'kg/m'),
                self::row('Weight per Foot', self::fmt($perM * 0.3048), 'kg/ft'),
                self::row('Quantity', self::fmt($q, 0), 'pieces'),
            ],
            'note' => 'Weight = Volume × Density (steel 7850 kg/m³). All dimensions use the selected unit.',
        ];
    }

    // ---- Beam steel (ISMB) ---------------------------------------------
    private static function defBeamSteel(): array
    {
        $sizes = [];
        foreach (self::ISMB_BEAM_STEEL as $s => $w) {
            $sizes[$s] = $s . ' (' . self::fmt($w, 1) . ' kg/m)';
        }
        return self::calc('beam_steel', 'Beam Steel Calculator', 'Weight of ISMB steel beams by size and length.', [
            self::select('beam_size', 'ISMB Beam Size', $sizes, 'ISMB 150'),
            self::num('length', 'Length', 'm'),
            self::num('quantity', 'Quantity', 'beams', ['default' => '1', 'integer' => true]),
        ]);
    }

    private static function runBeamSteel(array $n, array $v): array
    {
        $perM = self::ISMB_BEAM_STEEL[$v['beam_size']];
        $total = $perM * $n['length'] * $n['quantity'];
        return [
            'rows' => [
                self::row('Beam Size', $v['beam_size']),
                self::row('Weight per Meter', self::fmt($perM), 'kg/m'),
                self::row('Total Weight', self::fmt($total), 'kg'),
                self::row('Total', self::fmt($total / 1000), 'tons'),
            ],
            'note' => 'ISMB = Indian Standard Medium Weight Beam. Weight = kg/m × length × quantity.',
        ];
    }

    // ---- I-beam / girder -----------------------------------------------
    private static function defIBeam(): array
    {
        $sizes = [];
        foreach (self::ISMB_I_BEAM as $s => $w) {
            $sizes[$s] = $s . ' (' . self::fmt($w, 1) . ' kg/m)';
        }
        $custom = ['mode' => 'custom'];
        return self::calc('i_beam', 'I-Beam / Girder Calculator', 'Weight of standard ISMB or custom I-beams and girders.', [
            self::radio('mode', 'Beam', ['standard' => 'Standard Beam', 'custom' => 'Custom Dimensions'], 'standard'),
            self::select('beam_size', 'Beam Size (ISMB)', $sizes, 'ISMB 100', ['mode' => 'standard']),
            self::num('height', 'Height (H)', 'mm', ['show_if' => $custom]),
            self::num('flange_width', 'Flange Width (B)', 'mm', ['show_if' => $custom]),
            self::num('flange_thickness', 'Flange Thickness (T)', 'mm', ['show_if' => $custom]),
            self::num('web_thickness', 'Web Thickness (S)', 'mm', ['show_if' => $custom]),
            self::radio('length_unit', 'Length Unit', self::units(['m', 'cm', 'ft']), 'm'),
            self::num('length', 'Beam Length', null, ['unit_from' => 'length_unit']),
        ]);
    }

    private static function runIBeam(array $n, array $v): array
    {
        $len = self::m($n['length'], $v['length_unit']);
        if ($v['mode'] === 'standard') {
            $perM = self::ISMB_I_BEAM[$v['beam_size']];
        } else {
            [$H, $B, $T, $S] = [$n['height'] / 1000, $n['flange_width'] / 1000, $n['flange_thickness'] / 1000, $n['web_thickness'] / 1000];
            $web = $H - 2 * $T;
            if ($web <= 0) {
                return ['errors' => ['height' => 'Height must be greater than twice the flange thickness.']];
            }
            $perM = ((2 * $B * $T) + ($web * $S)) * 7850;
        }
        $kg = $perM * $len;
        $rows = [
            self::row('Total Weight', self::fmt($kg), 'kg'),
            self::row('Total Weight', self::fmt($kg / 1000), 'tons'),
        ];
        if ($v['mode'] === 'standard') {
            $rows[] = self::row('Beam Type', $v['beam_size']);
        }
        $rows[] = self::row('Weight per Meter', self::fmt($perM), 'kg/m');
        return ['rows' => $rows, 'note' => 'Weight = Volume × Steel Density (7850 kg/m³).'];
    }

    // ---- Channel bar (ISMC) --------------------------------------------
    private static function defChannelBar(): array
    {
        $sizes = [];
        foreach (self::ISMC as $s => $d) {
            $sizes[$s] = $s . ' (' . self::plain($d[0]) . ' kg/m)';
        }
        return self::calc('channel_bar', 'Channel Bar (ISMC)', 'Weight and cost of ISMC steel channels.', [
            self::select('size', 'ISMC Size', $sizes, 'ISMC 150'),
            self::num('length', 'Length', 'm'),
            self::num('quantity', 'Quantity', 'pcs', ['default' => '1', 'integer' => true]),
            self::num('rate', 'Rate per kg (optional)', null, ['required' => false, 'rule' => 'nonneg']),
        ], true);
    }

    private static function runChannelBar(array $n, array $v, string $cur): array
    {
        [$perM, $h, $w, $web, $flange] = self::ISMC[$v['size']];
        $total = $perM * $n['length'] * $n['quantity'];
        return [
            'rows' => self::weightCostRows($perM, $total, $n['rate'], $cur),
            'note' => $v['size'] . ': height ' . $h . ' mm, width ' . $w . ' mm, web ' . self::plain($web) . ' mm, flange ' . self::plain($flange) . ' mm.',
        ];
    }

    /** Weight per metre, total weight and optional cost rows (channel, tee, square bar). */
    private static function weightCostRows(float $perM, float $total, ?float $rate, string $cur): array
    {
        $rows = [
            self::row('Weight per Meter', self::fmt($perM), 'kg/m'),
            self::row('Total Weight', self::fmt($total), 'kg'),
        ];
        if ($rate !== null && $total * $rate > 0) {
            $rows[] = self::row('Total Cost', self::money($total * $rate, $cur));
        }
        return $rows;
    }

    // ---- T bar ---------------------------------------------------------
    private static function defTBar(): array
    {
        $inputs = [self::radio('type', 'T-Section Type', ['ISNT' => 'ISNT (Narrow)', 'ISHT' => 'ISHT (Heavy)', 'ISST' => 'ISST (Standard)'], 'ISNT')];
        foreach (self::TEES as $type => [$desc, $sizes]) {
            $opts = [];
            foreach ($sizes as $s => $d) {
                $opts[$s] = $s . ' (' . self::plain($d[2]) . ' kg/m)';
            }
            $default = $type === 'ISNT' ? 'ISNT 100' : (string) array_key_first($sizes);
            $inputs[] = self::select('size_' . strtolower($type), 'Size', $opts, $default, ['type' => $type]);
        }
        $inputs[] = self::num('length', 'Length', 'm');
        $inputs[] = self::num('quantity', 'Quantity', 'pcs', ['default' => '1', 'integer' => true]);
        $inputs[] = self::num('rate', 'Rate per kg (optional)', null, ['required' => false, 'rule' => 'nonneg']);
        return self::calc('t_bar', 'T Bar (Tee Section)', 'Weight and cost of Indian Standard tee sections.', $inputs, true);
    }

    private static function runTBar(array $n, array $v, string $cur): array
    {
        $type = $v['type'];
        $size = $v['size_' . strtolower($type)];
        [$depth, $flange, $perM] = self::TEES[$type][1][$size];
        $total = $perM * $n['length'] * $n['quantity'];
        return [
            'rows' => self::weightCostRows($perM, $total, $n['rate'], $cur),
            'note' => $size . ' (' . self::TEES[$type][0] . '): depth ' . $depth . ' mm, flange width ' . $flange . ' mm. As per IS SP 6 & IS 1173.',
        ];
    }

    // ---- Round bar -----------------------------------------------------
    private static function defRoundBar(): array
    {
        return self::calc('round_bar', 'Round Bar Calculator', 'Weight of mild steel round bars using D² ÷ 162.28.', [
            self::select('unit', 'Diameter Unit', self::units(['mm', 'cm', 'm', 'in']), 'mm'),
            self::num('diameter', 'Diameter', null, ['unit_from' => 'unit', 'hint' => 'Standard sizes: 6, 8, 10, 12, 16, 20, 25, 28, 32, 36, 40, 50 mm']),
            self::radio('length_unit', 'Length Unit', self::units(['m', 'ft']), 'm'),
            self::num('length', 'Length', null, ['unit_from' => 'length_unit']),
            self::num('quantity', 'Quantity', 'pieces', ['default' => '1', 'integer' => true]),
        ]);
    }

    private static function runRoundBar(array $n, array $v): array
    {
        $dMm = $n['diameter'] * ['mm' => 1.0, 'cm' => 10.0, 'm' => 1000.0, 'in' => 25.4][$v['unit']];
        $lenM = self::m($n['length'], $v['length_unit']);
        $perM = ($dMm * $dMm) / 162.28;
        $total = $perM * $lenM * $n['quantity'];
        return [
            'rows' => [
                self::row('Weight per Meter', self::fmt($perM, 3), 'kg/m'),
                self::row('Weight per Foot', self::fmt($perM * 0.3048, 3), 'kg/ft'),
                self::row('Total Weight', self::fmt($total, 3), 'kg'),
                self::row('Total', self::fmt($total / 1000, 3), 'tons'),
            ],
            'note' => 'Weight (kg/m) = D² ÷ 162.28, where D is the diameter in millimeters.',
        ];
    }

    // ---- Square bar ----------------------------------------------------
    private static function defSquareBar(): array
    {
        $mats = [];
        foreach (self::SQUARE_BAR_MATERIALS as $m => $d) {
            $mats[$m] = $m . ' (' . $d . ' kg/m³)';
        }
        return self::calc('square_bar', 'Square Bar', 'Weight, volume and cost of square steel bars.', [
            self::select('material', 'Material', $mats, 'Mild Steel'),
            self::num('size', 'Size (side length)', 'mm', ['hint' => 'Common sizes: 6, 8, 10, 12, 16, 20, 25, 32, 40, 50 mm']),
            self::num('length', 'Length', 'm'),
            self::num('quantity', 'Quantity', 'pcs', ['default' => '1', 'integer' => true]),
            self::num('rate', 'Rate per kg (optional)', null, ['required' => false, 'rule' => 'nonneg']),
        ], true);
    }

    private static function runSquareBar(array $n, array $v, string $cur): array
    {
        $s = $n['size'];
        $perM = 0.00785 * $s * $s * (self::SQUARE_BAR_MATERIALS[$v['material']] / 7850);
        $volume = ($s * $s * $n['length'] * $n['quantity']) / 1000000;
        $total = $perM * $n['length'] * $n['quantity'];
        $rows = self::weightCostRows($perM, $total, $n['rate'], $cur);
        array_splice($rows, 1, 0, [self::row('Total Volume', self::fmt($volume, 4), 'm³')]);
        return ['rows' => $rows, 'note' => 'Weight (kg) = 0.00785 × Size² × Length (mild steel), scaled by material density.'];
    }

    // ---- Round pipe ----------------------------------------------------
    private static function defRoundPipe(): array
    {
        $mats = [];
        foreach (self::PIPE_MATERIALS as $m => $d) {
            $mats[$m] = $m . ' (' . $d . ' kg/m³)';
        }
        $u = ['unit_from' => 'unit'];
        return self::calc('round_pipe', 'Round Pipe Calculator', 'Weight of hollow round pipes and tubes in steel, copper, aluminum or brass.', [
            self::radio('mode', 'Input Mode', ['wall' => 'Wall Thickness', 'inner' => 'Inner Diameter'], 'wall'),
            self::select('material', 'Material Type', $mats, 'Mild Steel'),
            self::select('unit', 'Unit of Measurement', self::units(['mm', 'cm', 'm', 'in', 'ft']), 'mm'),
            self::num('outer_diameter', 'Outer Diameter', null, $u),
            self::num('wall_thickness', 'Wall Thickness', null, $u + ['show_if' => ['mode' => 'wall']]),
            self::num('inner_diameter', 'Inner Diameter', null, $u + ['show_if' => ['mode' => 'inner']]),
            self::num('length', 'Length', null, $u),
            self::num('quantity', 'Quantity', 'pieces', ['default' => '1', 'integer' => true]),
        ]);
    }

    private static function runRoundPipe(array $n, array $v): array
    {
        $u = $v['unit'];
        $outer = self::m($n['outer_diameter'], $u);
        $inner = $v['mode'] === 'wall' ? $outer - 2 * self::m($n['wall_thickness'], $u) : self::m($n['inner_diameter'], $u);
        if ($inner >= $outer) {
            return ['errors' => ['inner_diameter' => 'Inner diameter must be less than outer diameter.']];
        }
        if ($inner < 0) {
            return ['errors' => ['wall_thickness' => 'Wall thickness must be less than half the outer diameter.']];
        }
        [$ro, $ri] = [$outer / 2, $inner / 2];
        $density = self::PIPE_MATERIALS[$v['material']];
        $section = M_PI * ($ro * $ro - $ri * $ri);
        $perM = $section * $density;
        $total = $section * self::m($n['length'], $u) * $density * $n['quantity'];
        return [
            'rows' => [
                self::row('Inner Diameter', self::fmt($inner * 1000, 3), 'mm'),
                self::row('Wall Thickness', self::fmt(($outer - $inner) * 1000 / 2, 3), 'mm'),
                self::row('Material', $v['material']),
                self::row('Weight per Meter', self::fmt($perM, 3), 'kg/m'),
                self::row('Weight per Foot', self::fmt($perM * 0.3048, 3), 'kg/ft'),
                self::row('Total Weight', self::fmt($total, 3), 'kg'),
                self::row('Total', self::fmt($total / 1000, 3), 'tons'),
            ],
            'note' => 'Volume = π × (ro² − ri²) × Length. Weight = Volume × Density (' . $density . ' kg/m³).',
        ];
    }

    // ==================================================================
    // AREA CALCULATOR (standalone, /app/calculators/area)
    // ==================================================================

    private const AREA_UNITS = [
        'm' => ['Meters', 'sq meters', 'meters', 1.0],
        'ft' => ['Feet', 'sq feet', 'feet', 10.7639],
        'cm' => ['Centimeters', 'sq centimeters', 'centimeters', 10000.0],
        'in' => ['Inches', 'sq inches', 'inches', 1550.0],
        'yd' => ['Yards', 'sq yards', 'yards', 1.19599],
    ];

    private static function defArea(): array
    {
        $units = array_map(fn(array $u): string => $u[0], self::AREA_UNITS);
        $s = fn($shapes): array => ['unit_from' => 'unit', 'show_if' => ['mode' => $shapes]];
        return self::calc('area', 'Area Calculator', 'Calculate the area and perimeter of common room shapes.', [
            self::radio('mode', 'Room Shape', ['rectangle' => 'Rectangle', 'square' => 'Square', 'l_shape' => 'L-Shape', 'circle' => 'Circle', 'triangle' => 'Triangle', 'trapezoid' => 'Trapezoid'], 'rectangle'),
            self::select('unit', 'Unit', $units, 'm'),
            self::num('length', 'Length', null, $s('rectangle')),
            self::num('width', 'Width', null, $s('rectangle')),
            self::num('side', 'Side Length', null, $s('square')),
            self::num('length1', 'Length 1', null, $s('l_shape') + ['hint' => 'Rectangle 1']),
            self::num('width1', 'Width 1', null, $s('l_shape') + ['hint' => 'Rectangle 1']),
            self::num('length2', 'Length 2', null, $s('l_shape') + ['hint' => 'Rectangle 2']),
            self::num('width2', 'Width 2', null, $s('l_shape') + ['hint' => 'Rectangle 2']),
            self::num('radius', 'Radius', null, $s('circle')),
            self::num('base', 'Base', null, $s('triangle')),
            self::num('base1', 'Base 1 (Top)', null, $s('trapezoid')),
            self::num('base2', 'Base 2 (Bottom)', null, $s('trapezoid')),
            self::num('height', 'Height', null, $s(['triangle', 'trapezoid'])),
        ]);
    }

    private static function runArea(array $n, array $v): array
    {
        [$area, $perimeter] = match ($v['mode']) {
            'rectangle' => [$n['length'] * $n['width'], 2 * ($n['length'] + $n['width'])],
            'square' => [$n['side'] ** 2, 4 * $n['side']],
            'l_shape' => [
                $n['length1'] * $n['width1'] + $n['length2'] * $n['width2'],
                2 * ($n['length1'] + $n['width1']) + 2 * ($n['length2'] + $n['width2']) - 2 * min($n['width1'], $n['width2']),
            ],
            'circle' => [M_PI * $n['radius'] ** 2, 2 * M_PI * $n['radius']],
            'triangle' => [($n['base'] * $n['height']) / 2, 3 * $n['base']],   // perimeter as coded (equilateral)
            default => [
                (($n['base1'] + $n['base2']) / 2) * $n['height'],
                $n['base1'] + $n['base2'] + 2 * sqrt((($n['base1'] - $n['base2']) / 2) ** 2 + $n['height'] ** 2),
            ],
        };
        [, $sq, $lin, $perM2] = self::AREA_UNITS[$v['unit']];
        $rows = [
            self::row('Area', self::fmtArea($area), $sq),
            self::row('Area (sq m)', self::fmtArea($area / $perM2), 'm²'),
        ];
        if ($perimeter > 0) {
            $rows[] = self::row('Perimeter', self::fmtArea($perimeter), $lin);
        }
        $notes = [
            'triangle' => 'Perimeter assumes an equilateral triangle (3 × base).',
            'trapezoid' => 'Perimeter assumes an isosceles trapezoid.',
            'l_shape' => 'Perimeter subtracts the shared edge (twice the smaller width).',
        ];
        return ['rows' => $rows, 'note' => $notes[$v['mode']] ?? null];
    }

    /** Area calculator rounding: >= 1 -> 2 dp, else 4 dp, trailing zeros stripped. */
    private static function fmtArea(float $x): string
    {
        return self::trimZeros(self::fmt($x, abs($x) >= 1 ? 2 : 4));
    }

    // ==================================================================
    // CONVERTERS
    // ==================================================================

    private static function defConverter(string $key): array
    {
        [$title, $word, $from, $to, $units] = self::CONVERTERS[$key];
        $names = $key === 'fuel' ? array_keys(self::FUEL) : array_keys($units);
        $opts = array_combine($names, $names);
        return [
            'id' => 'convert_' . $key, 'title' => $title,
            'intro' => 'Convert ' . $word . ' between ' . count($names) . ' common units.',
            'tab' => 'converter',
            'inputs' => [
                self::num('value', 'Value', null, ['default' => '1', 'rule' => 'any', 'required' => false, 'fallback' => 0.0]),
                self::select('from', 'From', $opts, $from),
                self::select('to', 'To', $opts, $to),
            ],
            'currency' => false,
        ];
    }

    private static function runConverter(string $key, array $n, array $v): array
    {
        $value = (float) ($n['value'] ?? 0.0);
        $names = $key === 'fuel' ? array_keys(self::FUEL) : array_keys(self::CONVERTERS[$key][4]);
        $convert = fn(string $to): float => self::convert($key, $value, $v['from'], $to);
        $result = $convert($v['to']);
        $shown = $v['value'] === '' ? '0' : $v['value'];
        $rows = [self::row('Result', self::fmtConv($result), $v['to'])];
        foreach ($names as $u) {
            if ($u !== $v['from']) {
                $rows[] = self::row($u, self::fmtConv($convert($u)));
            }
        }
        return ['rows' => $rows, 'note' => $shown . ' ' . $v['from'] . ' = ' . self::fmtConv($result) . ' ' . $v['to']];
    }

    /** Convert one value; linear factors, or the fuel economy formulas. */
    private static function convert(string $key, float $value, string $from, string $to): float
    {
        if ($value == 0.0) {
            return 0.0;
        }
        if ($key === 'fuel') {
            [$ft, $ff] = self::FUEL[$from];
            [$tt, $tf] = self::FUEL[$to];
            $l100 = $ft === 'consumption' ? $value * $ff : $ff / $value;
            return $tt === 'consumption' ? $l100 / $tf : $tf / $l100;
        }
        $f = self::CONVERTERS[$key][4];
        return $value * $f[$from] / $f[$to];
    }

    /** Converter rounding (Dart _formatResult), with US thousands grouping. */
    private static function fmtConv(float $v): string
    {
        if (!is_finite($v)) {
            return '—';
        }
        if ($v == 0.0) {
            return '0';
        }
        $a = abs($v);
        if ($a >= 1000000 || $a < 0.001) {
            return sprintf('%.4e', $v);   // e.g. 1.6093e+6, same shape as Dart toStringAsExponential(4)
        }
        return self::trimZeros(self::fmt($v, $a >= 1 ? 4 : 6));
    }
}
