<?php
/**
 * Create Blog Articles for Borehole Business
 * Based on content from veloxboreholes.com
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo = getDBConnection();

// Get first category (or create one if none exists)
$categoryStmt = $pdo->query("SELECT id, name FROM cms_categories LIMIT 1");
$category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    // Create a General/Tutorials category
    $pdo->exec("INSERT INTO cms_categories (name, slug, description) VALUES ('Tutorials', 'tutorials', 'Educational articles and tutorials')");
    $categoryId = $pdo->lastInsertId();
} else {
    $categoryId = $category['id'];
}

// Get admin user ID
$userStmt = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
$userId = $userStmt->fetchColumn() ?: 1;

// Article 1: Water Quality Testing
$article1 = [
    'title' => 'Why Regular Water Quality Testing is Essential for Your Borehole',
    'slug' => 'water-quality-testing-essential-for-borehole',
    'excerpt' => 'Regular water quality testing ensures your borehole water is safe for consumption. Learn about common contaminants, testing methods, and when to test your water supply.',
    'content' => '<h2>Understanding Water Quality in Boreholes</h2>
<p>Owning a borehole gives you access to a private water supply, but with that privilege comes the responsibility of ensuring your water is safe for consumption. Unlike municipal water systems that undergo regular testing, private borehole owners must take charge of their water quality monitoring.</p>

<h2>Why Test Your Borehole Water?</h2>
<p>Borehole water quality can change over time due to various factors including seasonal changes, nearby construction, agricultural activities, or natural geological processes. Regular testing helps you:</p>
<ul>
<li><strong>Protect Your Health:</strong> Identify harmful bacteria, chemicals, or heavy metals before they cause health issues</li>
<li><strong>Maintain Equipment:</strong> High mineral content can damage pumps, pipes, and appliances</li>
<li><strong>Ensure Taste and Odor:</strong> Detect issues that affect water palatability</li>
<li><strong>Comply with Regulations:</strong> Meet local health and safety standards</li>
</ul>

<h2>Common Contaminants to Watch For</h2>
<h3>Biological Contaminants</h3>
<p>Bacteria like E. coli, coliform, and other microorganisms can enter your borehole through:</p>
<ul>
<li>Surface water contamination</li>
<li>Cracked well casings</li>
<li>Improperly sealed wellheads</li>
<li>Nearby septic systems</li>
</ul>

<h3>Chemical Contaminants</h3>
<p>Chemical issues can include:</p>
<ul>
<li><strong>Iron and Manganese:</strong> Cause staining and metallic taste</li>
<li><strong>Hardness (Calcium/Magnesium):</strong> Lead to scale buildup in pipes</li>
<li><strong>Nitrates:</strong> Especially concerning for infants and pregnant women</li>
<li><strong>Arsenic and Heavy Metals:</strong> Naturally occurring in some geological formations</li>
</ul>

<h3>Physical Contaminants</h3>
<p>Sediment, turbidity, and color can indicate problems with your borehole structure or filtration system.</p>

<h2>When Should You Test Your Water?</h2>
<p>Experts recommend testing your borehole water:</p>
<ul>
<li><strong>Annually:</strong> For basic safety tests (bacteria, nitrates, pH, total dissolved solids)</li>
<li><strong>After Installation:</strong> Before first use</li>
<li><strong>After Repairs:</strong> Following any borehole maintenance or pump replacement</li>
<li><strong>When Issues Arise:</strong> If you notice changes in taste, odor, or color</li>
<li><strong>Seasonal Changes:</strong> After heavy rains or dry periods</li>
<li><strong>After Nearby Activities:</strong> Following construction, agricultural spraying, or industrial work</li>
</ul>

<h2>Water Testing Parameters</h2>
<h3>Basic Testing (Recommended Annually)</h3>
<ul>
<li>Total Coliform and E. coli</li>
<li>pH levels</li>
<li>Total Dissolved Solids (TDS)</li>
<li>Nitrates and Nitrites</li>
<li>Hardness</li>
<li>Iron and Manganese</li>
</ul>

<h3>Comprehensive Testing (Every 3-5 Years)</h3>
<ul>
<li>Heavy metals (lead, arsenic, mercury)</li>
<li>Pesticides and herbicides</li>
<li>Radon</li>
<li>Volatile Organic Compounds (VOCs)</li>
</ul>

<h2>How to Get Your Water Tested</h2>
<p>There are several options for water testing:</p>

<h3>Professional Laboratory Testing</h3>
<p>Certified laboratories provide the most accurate results. They can test for a wide range of contaminants and provide detailed reports with recommendations.</p>

<h3>Home Testing Kits</h3>
<p>Simple test kits are available for basic parameters like pH, hardness, and chlorine. While convenient, they don\'t replace professional testing for safety-critical parameters.</p>

<h3>Water Treatment Companies</h3>
<p>Many borehole service providers offer water testing as part of their services, often including recommendations for treatment solutions if issues are found.</p>

<h2>Interpreting Test Results</h2>
<p>Understanding your test results is crucial:</p>
<ul>
<li><strong>Compare to Standards:</strong> Results should be compared to local drinking water standards (WHO guidelines, local health authority standards)</li>
<li><strong>Identify Trends:</strong> Keep records of all tests to identify changes over time</li>
<li><strong>Take Action:</strong> If contaminants exceed safe levels, implement appropriate treatment solutions</li>
</ul>

<h2>Treatment Solutions for Common Issues</h2>
<h3>Bacterial Contamination</h3>
<p>Shock chlorination is typically the first step, followed by UV sterilization or continuous chlorination systems for ongoing protection.</p>

<h3>High Mineral Content</h3>
<p>Water softeners can address hardness, while iron filters can remove excess iron and manganese.</p>

<h3>Chemical Contaminants</h3>
<p>Reverse osmosis systems, activated carbon filters, or specialized treatment systems may be required depending on the specific contaminants.</p>

<h2>Maintaining Water Quality</h2>
<p>Beyond testing, maintain water quality by:</p>
<ul>
<li>Keeping your borehole properly sealed and maintained</li>
<li>Ensuring adequate distance from contamination sources</li>
<li>Regular maintenance of pumps and filtration systems</li>
<li>Proper disposal of hazardous materials</li>
<li>Monitoring nearby activities that could affect groundwater</li>
</ul>

<h2>Conclusion</h2>
<p>Regular water quality testing is not just a recommendation—it\'s a crucial responsibility for borehole owners. By staying proactive about testing and addressing issues promptly, you can ensure your family has access to safe, clean water for years to come. Don\'t wait for problems to appear; make water testing a regular part of your borehole maintenance routine.</p>

<p><strong>Need help with water testing or treatment?</strong> Contact professional borehole service providers who can guide you through the testing process and recommend appropriate solutions based on your specific situation.</p>',
    'seo_title' => 'Water Quality Testing for Boreholes: Essential Guide | ABBIS',
    'seo_description' => 'Learn why regular water quality testing is essential for borehole owners. Discover common contaminants, testing schedules, and treatment solutions to ensure safe drinking water.',
];

// Article 2: Choosing the Right Pump
$article2 = [
    'title' => 'Choosing the Right Pump for Your Borehole: A Complete Guide',
    'slug' => 'choosing-right-pump-for-borehole-complete-guide',
    'excerpt' => 'Selecting the right pump for your borehole is crucial for efficient water supply. This guide covers pump types, sizing, installation considerations, and maintenance tips.',
    'content' => '<h2>Introduction to Borehole Pumps</h2>
<p>Selecting the right pump for your borehole is one of the most important decisions you\'ll make as a borehole owner. The right pump ensures reliable water supply, optimal energy efficiency, and long-term performance. This comprehensive guide will help you make an informed decision.</p>

<h2>Understanding Your Borehole Requirements</h2>
<p>Before choosing a pump, you need to understand your specific requirements:</p>

<h3>Borehole Depth</h3>
<p>The depth of your borehole determines the type of pump you need. Measure from the ground level to the bottom of the borehole, and also note the static water level (where water naturally sits) and pumping level (lowest point water reaches during pumping).</p>

<h3>Water Yield</h3>
<p>The yield of your borehole (measured in liters per minute or gallons per hour) affects pump sizing. A pump that\'s too large for your yield can cause the borehole to run dry, while an undersized pump won\'t meet your water needs.</p>

<h3>Water Usage Requirements</h3>
<p>Consider your daily water needs:</p>
<ul>
<li>Household size and daily consumption</li>
<li>Agricultural or irrigation needs</li>
<li>Commercial or industrial usage</li>
<li>Peak demand periods</li>
</ul>

<h2>Types of Borehole Pumps</h2>

<h3>Submersible Pumps</h3>
<p><strong>Best for:</strong> Deep boreholes (20+ meters), continuous operation, high efficiency</p>
<p>Submersible pumps are installed inside the borehole, submerged in water. They offer several advantages:</p>
<ul>
<li>Highly efficient operation</li>
<li>Quiet operation (pump is underground)</li>
<li>Less prone to cavitation</li>
<li>Suitable for deep installations</li>
<li>Low maintenance requirements</li>
</ul>
<p><strong>Considerations:</strong> More expensive initially, requires professional installation, harder to access for repairs</p>

<h3>Surface Pumps</h3>
<p><strong>Best for:</strong> Shallow boreholes (less than 7-8 meters), temporary installations, low water tables</p>
<p>Surface pumps sit above ground and draw water up through a suction pipe:</p>
<ul>
<li>Lower initial cost</li>
<li>Easier to access for maintenance</li>
<li>Portable options available</li>
<li>Suitable for shallow wells</li>
</ul>
<p><strong>Considerations:</strong> Limited suction depth, can be noisy, less efficient for deep installations, requires priming</p>

<h3>Booster Pumps</h3>
<p><strong>Best for:</strong> Increasing pressure in existing systems, multi-story buildings, long pipe runs</p>
<p>Booster pumps are used to increase water pressure in distribution systems, often working in conjunction with submersible pumps.</p>

<h2>Pump Sizing: Getting It Right</h2>

<h3>Flow Rate (GPM/LPM)</h3>
<p>Calculate your required flow rate based on:</p>
<ul>
<li>Number of fixtures in use simultaneously</li>
<li>Peak usage times</li>
<li>Irrigation or agricultural needs</li>
</ul>
<p><strong>General Guidelines:</strong></p>
<ul>
<li>Small household: 10-15 GPM (38-57 LPM)</li>
<li>Medium household: 15-20 GPM (57-76 LPM)</li>
<li>Large household/farm: 20-40+ GPM (76-150+ LPM)</li>
</ul>

<h3>Pressure Requirements</h3>
<p>Most household systems require 40-60 PSI (2.8-4.1 bar) of pressure. Consider:</p>
<ul>
<li>Height from pump to highest fixture</li>
<li>Pipe length and friction losses</li>
<li>Pressure tank settings</li>
</ul>

<h3>Horsepower (HP) Selection</h3>
<p>Pump horsepower depends on:</p>
<ul>
<li>Total dynamic head (TDH) - the total height the pump must lift water</li>
<li>Required flow rate</li>
<li>Borehole depth</li>
</ul>
<p>Always consult with a professional to calculate exact requirements. Oversizing can waste energy and damage your borehole, while undersizing leads to inadequate water supply.</p>

<h2>Key Features to Consider</h2>

<h3>Motor Quality</h3>
<p>Look for pumps with:</p>
<ul>
<li>Stainless steel construction (for corrosion resistance)</li>
<li>Thermal protection (prevents motor burnout)</li>
<li>Energy-efficient motors (look for efficiency ratings)</li>
<li>Durable seals and bearings</li>
</ul>

<h3>Pump Controls</h3>
<p>Modern pumps often include:</p>
<ul>
<li><strong>Pressure Switches:</strong> Automatically start/stop based on pressure</li>
<li><strong>Variable Speed Drives:</strong> Adjust pump speed based on demand (more efficient)</li>
<li><strong>Protection Systems:</strong> Dry-run protection, overvoltage protection, phase monitoring</li>
<li><strong>Timers:</strong> Schedule pump operation, prevent continuous running</li>
</ul>

<h3>Installation Requirements</h3>
<p>Consider:</p>
<ul>
<li>Borehole diameter (pump must fit comfortably)</li>
<li>Cable length requirements</li>
<li>Discharge pipe size</li>
<li>Ground conditions for surface installations</li>
</ul>

<h2>Installation Best Practices</h2>

<h3>Professional Installation Recommended</h3>
<p>While some experienced DIYers may install surface pumps, submersible pump installation should always be done by professionals due to:</p>
<ul>
<li>Electrical safety requirements</li>
<li>Proper depth calculations</li>
<li>Correct pipe and cable installation</li>
<li>Pressure tank and control setup</li>
<li>System testing and calibration</li>
</ul>

<h3>Installation Depth</h3>
<p>For submersible pumps:</p>
<ul>
<li>Install below the lowest expected water level</li>
<li>Leave adequate clearance from the bottom (minimum 3-5 feet)</li>
<li>Consider seasonal water level variations</li>
</ul>

<h3>Electrical Considerations</h3>
<ul>
<li>Proper voltage and phase requirements</li>
<li>Adequate electrical supply capacity</li>
<li>Ground fault protection</li>
<li>Proper cable sizing for voltage drop</li>
<li>Weatherproof connections</li>
</ul>

<h2>Maintenance and Longevity</h2>

<h3>Regular Maintenance Tasks</h3>
<ul>
<li><strong>Annual Inspections:</strong> Check electrical connections, pressure settings, system performance</li>
<li><strong>Water Quality:</strong> Poor water quality can damage pumps faster</li>
<li><strong>Pressure Tank:</strong> Check air pressure, replace bladder if needed</li>
<li><strong>Filters:</strong> Clean or replace sediment filters regularly</li>
<li><strong>System Monitoring:</strong> Watch for unusual sounds, pressure changes, or energy consumption</li>
</ul>

<h3>Extending Pump Life</h3>
<ul>
<li>Proper sizing prevents overworking</li>
<li>Water treatment reduces wear from minerals</li>
<li>Protection against dry running</li>
<li>Timers prevent continuous operation</li>
<li>Regular maintenance catches issues early</li>
</ul>

<h3>Signs You Need a New Pump</h3>
<ul>
<li>Frequent cycling or pressure problems</li>
<li>Unusual noises or vibrations</li>
<li>Increased energy consumption</li>
<li>Reduced water flow</li>
<li>Motor failures or frequent repairs</li>
</ul>

<h2>Energy Efficiency Considerations</h2>
<p>Pump operation can be a significant energy cost. Consider:</p>
<ul>
<li><strong>Variable Speed Pumps:</strong> Adjust to actual demand, saving energy</li>
<li><strong>High-Efficiency Motors:</strong> Look for energy-efficient ratings</li>
<li><strong>Proper Sizing:</strong> Right-sized pumps operate more efficiently</li>
<li><strong>Pressure Tank Sizing:</strong> Larger tanks reduce pump cycling</li>
<li><strong>Timers and Controls:</strong> Schedule operation during off-peak hours if applicable</li>
</ul>

<h2>Cost Considerations</h2>
<p>When budgeting for a borehole pump, consider:</p>
<ul>
<li><strong>Initial Purchase:</strong> Pump cost varies significantly based on type and capacity</li>
<li><strong>Installation:</strong> Professional installation costs</li>
<li><strong>Accessories:</strong> Pressure tanks, controls, piping, electrical work</li>
<li><strong>Operating Costs:</strong> Energy consumption over pump lifetime</li>
<li><strong>Maintenance:</strong> Ongoing maintenance and potential repairs</li>
<li><strong>Lifecycle:</strong> Higher quality pumps last longer, providing better long-term value</li>
</ul>

<h2>Common Mistakes to Avoid</h2>
<ol>
<li><strong>Oversizing:</strong> Bigger isn\'t always better - can damage borehole and waste energy</li>
<li><strong>Ignoring Water Quality:</strong> Poor water quality can significantly reduce pump life</li>
<li><strong>Improper Installation:</strong> DIY installations without proper knowledge can be dangerous and costly</li>
<li><strong>Neglecting Maintenance:</strong> Regular maintenance prevents costly repairs</li>
<li><strong>Wrong Pump Type:</strong> Choosing surface pump for deep borehole or vice versa</li>
<li><strong>Ignoring Protection:</strong> Lack of dry-run protection can destroy pumps quickly</li>
</ol>

<h2>Conclusion</h2>
<p>Choosing the right pump for your borehole requires careful consideration of your specific needs, borehole characteristics, and long-term goals. While cost is important, investing in a properly sized, high-quality pump with professional installation will provide reliable water supply and better long-term value.</p>

<p><strong>Ready to choose your pump?</strong> Consult with experienced borehole professionals who can assess your specific situation, perform necessary calculations, and recommend the best pump solution for your needs. Proper planning and professional guidance ensure you get a system that serves you well for years to come.</p>

<h2>FAQ</h2>
<p><strong>Q: How often should I replace my borehole pump?</strong><br>
A: With proper maintenance, quality submersible pumps typically last 8-15 years. Surface pumps may need replacement every 5-10 years depending on usage and maintenance.</p>

<p><strong>Q: Can I install a pump myself?</strong><br>
A: Surface pumps for shallow installations may be DIY-friendly, but submersible pumps require professional installation due to safety and technical requirements.</p>

<p><strong>Q: What\'s the difference between 1-phase and 3-phase pumps?</strong><br>
A: 1-phase pumps are suitable for residential use with standard household electrical supply. 3-phase pumps are typically for larger commercial applications and offer better efficiency for high-capacity needs.</p>',
    'seo_title' => 'Choosing the Right Borehole Pump: Complete Guide 2024 | ABBIS',
    'seo_description' => 'Complete guide to choosing the right pump for your borehole. Learn about pump types, sizing, installation, and maintenance for optimal water supply.',
];

// Insert articles
$articles = [$article1, $article2];

foreach ($articles as $article) {
    // Check if article already exists
    $checkStmt = $pdo->prepare("SELECT id FROM cms_posts WHERE slug = ?");
    $checkStmt->execute([$article['slug']]);
    
    if ($checkStmt->fetch()) {
        echo "Article '{$article['title']}' already exists. Skipping...\n";
        continue;
    }
    
    // Insert article
    $stmt = $pdo->prepare("
        INSERT INTO cms_posts 
        (title, slug, content, excerpt, category_id, status, seo_title, seo_description, published_at, created_by) 
        VALUES (?, ?, ?, ?, ?, 'published', ?, ?, NOW(), ?)
    ");
    
    $stmt->execute([
        $article['title'],
        $article['slug'],
        $article['content'],
        $article['excerpt'],
        $categoryId,
        $article['seo_title'],
        $article['seo_description'],
        $userId
    ]);
    
    $postId = $pdo->lastInsertId();
    echo "✅ Created article: '{$article['title']}' (ID: $postId)\n";
}

echo "\n✅ Blog articles created successfully!\n";
echo "You can view them at: /cms/admin/posts.php\n";

