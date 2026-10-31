<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace format_streamdeck\local;

/**
 * Tests for the standard / TV display.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(display_mode::class)]
final class display_mode_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        unset($_GET['display'], $_COOKIE[display_mode::COOKIE]);
        \core_useragent::instance(true, '');
        parent::tearDown();
    }

    /**
     * User agents of TV browsers and sticks, and of browsers that are not TVs.
     *
     * @return array
     */
    public static function user_agent_provider(): array {
        return [
            'Samsung Tizen' => ['Mozilla/5.0 (SMART-TV; LINUX; Tizen 6.5) SamsungBrowser/4.0 TV Safari/537.36', true],
            'LG webOS' => ['Mozilla/5.0 (Web0S; Linux/SmartTV) Chrome/94.0 Safari/537.36 WebAppManager', true],
            'LG NetCast' => ['Mozilla/5.0 (DirectFB; Linux armv7l) LG Browser/5.00.00 (+mouse+3D; NetCast)', true],
            'Sony BRAVIA' => ['Mozilla/5.0 (Linux; Andr0id 9; BRAVIA 4K UR2) Chrome/90.0 Safari/537.36', true],
            'Android TV' => ['Mozilla/5.0 (Linux; Android 12; Android TV) Chrome/112.0 Safari/537.36', true],
            'Fire TV' => ['Mozilla/5.0 (Linux; Android 9; AFTMM Build/PS7633) Chrome/108.0 Safari/537.36', true],
            'Chromecast' => ['Mozilla/5.0 (X11; Linux aarch64) Chrome/114.0 Safari/537.36 CrKey/1.56.500000', true],
            'HbbTV' => ['Mozilla/5.0 (Linux armv7l) HbbTV/1.5.1 (+DRM; Philips; TV; 2021) Chrome/79.0', true],
            'Roku' => ['Roku/DVP-12.0 (12.0.0.4182-88)', true],
            'Mac Safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) Version/18.0 Safari/605.1.15', false],
            'Windows Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36', false],
            'Android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) Chrome/130.0 Mobile Safari/537.36', false],
            'iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_6 like Mac OS X) Version/17.6 Mobile Safari/604.1', false],
            'Moodle App' => ['Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile Safari/537.36 MoodleMobile 5.0', false],
        ];
    }

    /**
     * TV browsers are recognised; desktops, phones and tablets are not.
     *
     * @param string $useragent
     * @param bool $istv
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('user_agent_provider')]
    public function test_is_tv_browser(string $useragent, bool $istv): void {
        $this->assertSame($istv, display_mode::is_tv_browser($useragent));
    }

    /**
     * A ?display= link wins, then this device's cookie, then TV detection, then standard.
     */
    public function test_get_current_mode(): void {
        $tv = 'Mozilla/5.0 (SMART-TV; LINUX; Tizen 6.5) SamsungBrowser/4.0 TV Safari/537.36';
        $desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';

        \core_useragent::instance(true, $desktop);
        $this->assertSame(display_mode::STANDARD, display_mode::get_current_mode());

        \core_useragent::instance(true, $tv);
        $this->assertSame(display_mode::TV, display_mode::get_current_mode());

        $_COOKIE[display_mode::COOKIE] = display_mode::STANDARD;
        $this->assertSame(display_mode::STANDARD, display_mode::get_current_mode());

        $_GET['display'] = display_mode::TV;
        $this->assertSame(display_mode::TV, display_mode::get_current_mode());

        $_GET['display'] = 'cinema';
        $_COOKIE[display_mode::COOKIE] = 'cinema';
        $this->assertSame(display_mode::TV, display_mode::get_current_mode());
    }

    /**
     * The view menu always offers the display, and the appearance only when the learner can choose it.
     */
    public function test_view_menu(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $menu = colour_mode::export_menu();
        $this->assertTrue($menu['canchoosecolour']);
        $this->assertSame(['standard', 'tv'], array_column($menu['displayoptions'], 'mode'));
        $this->assertStringContainsString('SameSite=Lax', $menu['cookieattributes']);

        $this->setGuestUser();
        $menu = colour_mode::export_menu();
        $this->assertFalse($menu['canchoosecolour']);
        $this->assertCount(2, $menu['displayoptions']);
    }
}
