<?php
/**
 * Markdown Rewritten - A rewrite of the PHP Markdown code
 *
 * PHP 5.3
 *
 * @author     Pieter Hordijk <info@pieterhordijk.com> (Markdown Rewritten)
 * @copyright  Copyright (c) 2012 Pieter Hordijk
 * @author     Michel Fortin <http://michelf.com/projects/php-markdown> (PHP Markdown)
 * @copyright  Copyright (c) 2012 Michel Fortin
 * @author     John Gruber <http://daringfireball.net/projects/markdown> (Original Markdown)
 * @copyright  Copyright (c) 2012 John Gruber
 * @license    Markdown is free software, available under the terms of the
 *             BSD-style open source license reproduced below, or, at your
 *             option, under the GNU General Public License version 2
 *             or a later version.
 *
 *             Redistribution and use in source and binary forms, with or without
 *             modification, are permitted provided that the following conditions
 *             are met:
 *
 *             - Redistributions of source code must retain the above copyright
 *               notice, this list of conditions and the following disclaimer.
 *
 *             - Redistributions in binary form must reproduce the above copyright
 *               notice, this list of conditions and the following disclaimer in
 *               the documentation and/or other materials provided with the
 *               distribution.
 *
 *             - Neither the name “PHP Markdown” nor the names of its contributors
 *               may be used to endorse or promote products derived from this
 *               software without specific prior written permission.
 *
 *             This software is provided by the copyright holders and
 *             contributors “as is” and any express or implied warranties,
 *             including, but not limited to, the implied warranties of
 *             merchantability and fitness for a particular purpose are
 *             disclaimed. In no event shall the copyright owner or contributors
 *             be liable for any direct, indirect, incidental, special, exemplary,
 *             or consequential damages (including, but not limited to,
 *             procurement of substitute goods or services; loss of use, data,
 *             or profits; or business interruption) however caused and on any
 *             theory of liability, whether in contract, strict liability, or
 *             tort (including negligence or otherwise) arising in any way out
 *             of the use of this software, even if advised of the possibility
 *             of such damage.
 * @version    1.0.0
 */

require_once 'Markdown.php';

class MarkdownTest extends PHPUnit_Framework_TestCase
{
    public function testRemoveBomWithThreeBomCharacters()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("\xEF\xBB\xBFSome text!"));
    }

    public function testRemoveBomWithSubstituteCharacter()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("\x1ASome text!"));
    }

    public function testRemoveBomWithThreeBomCharactersNotAtStart()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text\xEF\xBB\xBF!</p>\n", $markdown->parse("Some text\xEF\xBB\xBF!"));
    }

    public function testNormalizeLinebreaksWithWindowsStyle()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("Some text!\r\n"));
    }

    public function testNormalizeLinebreaksWithOldMacStyle()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("Some text!\r"));
    }

    public function testNormalizeLinebreaksWithNixStyle()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("Some text!\n"));
    }

    public function testNormalizeLinebreaksWithWindowsStyleInsideText()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text\n!</p>\n", $markdown->parse("Some text\r\n!"));
    }

    public function testNormalizeLinebreaksWithoutLinebreaks()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("Some text!"));
    }

    public function testNormalizeTabs()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some     te      xt!</p>\n", $markdown->parse("Some\t te \t xt!"));
    }

    // fails, need to look into
    public function testNormalizeTabsInText()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some     te      xt!</p>\n", $markdown->parse("Some\t te\txt!"));
    }

    public function testNormalizeTabsWithoutTabs()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p>Some text!</p>\n", $markdown->parse("Some text!"));
    }

    public function testItalicAsterisk()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><em>Some text!</em></p>\n", $markdown->parse("*Some text!*"));
    }

    public function testItalicUnderscore()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><em>Some text!</em></p>\n", $markdown->parse("_Some text!_"));
    }

    public function testItalicMixed()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><em>So</em>me te<em>xt!</em></p>\n", $markdown->parse("*So*me te_xt!_"));
    }

    public function testBoldAsterisk()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><strong>Some text!</strong></p>\n", $markdown->parse("**Some text!**"));
    }

    public function testBoldUnderscore()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><strong>Some text!</strong></p>\n", $markdown->parse("__Some text!__"));
    }

    public function testBoldMixed()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><strong>So</strong>me te<strong>xt!</strong></p>\n", $markdown->parse("**So**me te__xt!__"));
    }

    public function testBoldAndItalicMixed()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><strong>So</strong>me te<em>xt!</em></p>\n", $markdown->parse("**So**me te_xt!_"));
    }

    public function testBoldAndItalicSuperMixed()
    {
        $markdown = new Markdown();

        $this->assertEquals("<p><strong>S</strong>o<em>m</em>e <strong>te</strong>xt<em>!</em></p>\n", $markdown->parse("**S**o_m_e __te__xt*!*"));
    }

    public function testSetextHeader1()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h1>Some text!</h1>\n", $markdown->parse("Some text!\n=========="));
    }

    public function testSetextHeader2()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h2>Some text!</h2>\n", $markdown->parse("Some text!\n---------"));
    }

    public function testAtxHeader1()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h1>Some text!</h1>\n", $markdown->parse("# Some text!"));
    }

    public function testAtxHeader1WithEndHashes()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h1>Some text!</h1>\n", $markdown->parse("# Some text! #"));
    }

    public function testAtxHeader2()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h2>Some text!</h2>\n", $markdown->parse("## Some text!"));
    }

    public function testAtxHeader2WithEndHashes()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h2>Some text!</h2>\n", $markdown->parse("## Some text! ##"));
    }

    public function testAtxHeader3()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h3>Some text!</h3>\n", $markdown->parse("### Some text!"));
    }

    public function testAtxHeader3WithEndHashes()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h3>Some text!</h3>\n", $markdown->parse("### Some text! ###"));
    }

    public function testAtxHeader4()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h4>Some text!</h4>\n", $markdown->parse("#### Some text!"));
    }

    public function testAtxHeader4WithEndHashes()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h4>Some text!</h4>\n", $markdown->parse("#### Some text! ####"));
    }

    public function testAtxHeader5()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h5>Some text!</h5>\n", $markdown->parse("##### Some text!"));
    }

    public function testAtxHeader5WithEndHashes()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h5>Some text!</h5>\n", $markdown->parse("##### Some text! #####"));
    }

    public function testAtxHeader6()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h6>Some text!</h6>\n", $markdown->parse("###### Some text!"));
    }

    public function testAtxHeader6WithEndHashes()
    {
        $markdown = new Markdown();

        $this->assertEquals("<h6>Some text!</h6>\n", $markdown->parse("###### Some text! ######"));
    }
}