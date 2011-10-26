<?php
/** PHP Weathermap 0.98
 * Copyright Howard Jones, 2005-2011 howie@thingy.com
 * http://www.network-weathermap.com/
 * Released under the GNU Public License
 *
 */

require_once 'HTML_ImageMap.class.php';

class WeatherMapLink extends WeatherMapItem
{
    var $owner, $name;
    var $id;
    var $maphtml;
    var $a, $b; // the ends - references to nodes
    var $width, $arrowstyle, $linkstyle;
    var $bwfont, $labelstyle, $labelboxstyle;
    var $zorder;
    var $overliburl = array ();
    var $infourl = array ();
    var $notes;
    var $overlibcaption = array ();
    var $overlibwidth, $overlibheight;
    var $bandwidth_in, $bandwidth_out;
    var $max_bandwidth_in, $max_bandwidth_out;
    var $max_bandwidth_in_cfg, $max_bandwidth_out_cfg;
    var $targets = array ();
    var $a_offset, $b_offset;
    var $in_ds, $out_ds;
    var $colours = array ();
    var $selected;
    var $inpercent, $outpercent;
    var $inherit_fieldlist;
    var $vialist = array ();
    var $viastyle;
    var $usescale, $duplex;
    var $scaletype;
    var $outlinecolour;
    var $bwoutlinecolour;
    var $bwboxcolour;
    var $splitpos;
    var $commentfont;
    var $notestext = array ();
    var $inscalekey, $outscalekey;
    var $inscaletag, $outscaletag;
    var $commentfontcolour;
    var $commentstyle;
    var $bwfontcolour;
    var $comments = array ();
    var $bwlabelformats = array ();
    var $curvepoints;
    var $labeloffset_in, $labeloffset_out;
    var $commentoffset_in, $commentoffset_out;
    var $template;

    // these are the calculated positions for various things, so that the
    // comment and bwlabel drawing code doesn't need to know about
    // curvepoints, and so that we don't *have* to use curvepoints
    var $comment_x = array();
    var $comment_y = array();
    var $comment_angle = array();
    var $label_x = array();
    var $label_y = array();
    var $label_angle = array();
    var $mid_x, $mid_y;
    var $commenttext = array();
    var $commentlength = array();
    var $commentheight = array();
    var $calculatedwidths = array();

    function WeatherMapLink()
    {
        $this->inherit_fieldlist = array (
            'my_default' => null,
            'width' => 7,
            'commentfont' => 1,
            'bwfont' => 2,
            'template' => ':: DEFAULT ::',
            'splitpos' => 50,
            'labeloffset_out' => 25,
            'labeloffset_in' => 75,
            'commentoffset_out' => 5,
            'commentoffset_in' => 95,
            'commentstyle' => 'edge',
            'arrowstyle' => 'classic',
            'viastyle' => 'curved',
            'usescale' => 'DEFAULT',
            'scaletype' => 'percent',
            'targets' => array (),
            'duplex' => 'full',
            'infourl' => array (
                '',
                ''
            ),
            'notes' => array (),
            'hints' => array (),
            'comments' => array (
                '',
                ''
            ),
            'bwlabelformats' => array (
                FMT_PERC_IN,
                FMT_PERC_OUT
            ),
            'overliburl' => array (
                array (),
                array ()
            ),
            'notestext' => array (
                IN => '',
                OUT => ''
            ),
            'labelstyle' => 'percent',
            'labelboxstyle' => 'classic',
            'linkstyle' => 'twoway',
            'overlibwidth' => 0,
            'overlibheight' => 0,
            'outlinecolour' => array (
                0,
                0,
                0
            ),
            'bwoutlinecolour' => array (
                0,
                0,
                0
            ),
            'bwfontcolour' => array (
                0,
                0,
                0
            ),
            'bwboxcolour' => array (
                255,
                255,
                255
            ),
            'commentfontcolour' => array (
                192,
                192,
                192
            ),
            'inpercent' => 0,
            'outpercent' => 0,
            'inscalekey' => '',
            'outscalekey' => '',
            'a_offset' => 'C',
            'b_offset' => 'C',
            'zorder' => 300,
            'overlibcaption' => array (
                '',
                ''
            ),
            'max_bandwidth_in' => 100000000,
            'max_bandwidth_out' => 100000000,
            'max_bandwidth_in_cfg' => '100M',
            'max_bandwidth_out_cfg' => '100M'
        );

    }

    function Reset(&$newowner)
    {
        $this->owner = $newowner;

        $template = $this->template;
        
        if (false === isset($this->template)) {
            $template = 'DEFAULT';
        }
        
        wm_debug("Resetting $this->name with $template\n");

        // the internal default-default gets it's values from inherit_fieldlist
        // everything else comes from a link object - the template.
        if ($this->name === ':: DEFAULT ::') {
            foreach (array_keys($this->inherit_fieldlist) as $fld) {
                $this->$fld = $this->inherit_fieldlist[$fld];
            }
        } else {            
            $this->CopyFrom($this->owner->links[$template]);
        }
        $this->template = $template;

        // to stop the editor tanking, now that colours are decided earlier in ReadData
        $this->colours[IN] = new WMColour(192, 192, 192);
        $this->colours[OUT] = new WMColour(192, 192, 192);
        $this->id = $newowner->next_id++;
    }

    function my_type()
    {
        return 'LINK';
    }

    function CopyFrom(&$source)
    {
        wm_debug("Initialising LINK ".$this->name." from ".$source->name."\n");
        assert('is_object($source)');

        foreach (array_keys($this->inherit_fieldlist) as $fld) {
            if ($fld !== 'template') {
                $this->$fld = $source->$fld;
            }
        }
    }

    function GetDirectionList()
    {
        if ($this->linkstyle == "oneway") {
            $dirs = array (OUT);
        } else {
            $dirs = array (
                OUT,
                IN
            );
        }

        return ($dirs);
    }

    /**
     * Figure out what the actual comment text will be, and how big it is.
     */
    function ProcessComments()
    {
        $dirs = $this->GetDirectionList();
        
        foreach ($dirs as $dir) {

            $comment = $this->owner->ProcessString($this->comments[$dir], $this);
            # print "COMMENT: $comment";

            if ($this->owner->get_hint('screenshot_mode') == 1) {
                $comment = wm_screenshotify($comment);
            }
            
            $this->commenttext[$dir] = $comment;

            $textheight = 0;
            $textlength = 0;

            if(strlen($comment) > 0) {
                list($textlength, $textheight) =
                    $this->owner->myimagestringsize($this->commentfont, $comment);
            }
             
             $this->commentlength[$dir] = $textlength;
             $this->commentheight[$dir] = $textheight;
        }
    }

    function CalculateCommentPosition($widths)
    {
        $dirs = $this->GetDirectionList();

        // nudge pushes the comment out along the link arrow a little bit
        // (otherwise there are more problems with text disappearing underneath links)

        $nudgeout = 0;
        $nudgealong = 0;

        $nudgealong = intval($this->get_hint("comment_nudgealong"));
        $nudgeout = intval($this->get_hint("comment_nudgeout"));

        $commentpos = array();
        $commentpos[OUT] = $this->commentoffset_out;
        $commentpos[IN] = $this->commentoffset_in;
        
        // this is a much simpler process when there is no
        // curve/vias involved. 
        if(count($this->vialist) > 0) {
            $curvepoints = &$this->curvepoints;
            $last = count($curvepoints) - 1;

            $totaldistance = $curvepoints[$last][2];

            $start = array();

            $start[OUT] = 0;
            $start[IN] = $last;
        }
        else
        {
            // do the simpler calculation for a straight link
            $d = new WMVector($this->b->x - $this->a->x, $this->b->y - $this->a->y);
            $totaldistance = $d->length();
            $d->normalise();
            $n = $d->get_normal();
        }


            foreach ($dirs as $dir) {                

                $extra_percent = $commentpos[$dir];
                $extra = ($totaldistance * ($extra_percent / 100));

                if(count($this->vialist) > 0) {

                    list($x, $y, $comment_index, $angle) =
                        find_distance_coords_angle($curvepoints, $extra);


                    if (($comment_index != 0) && (($x != $curvepoints[$comment_index][0])
                        || ($y != $curvepoints[$comment_index][1]))) {
                        $d = new WMVector($x - $curvepoints[$comment_index][0], $y - $curvepoints[$comment_index][1]);
                    } else {
                        $d = new WMVector($curvepoints[$comment_index + 1][0] - $x, $curvepoints[$comment_index + 1][1] - $y);
                    }
                    $edge = new WMPoint($x, $y);
                }
                else
                {
                    // this is what would have come from the spine, in a curved link
                    $edge = new WMPoint($this->comment_x[$dir], $this->comment_y[$dir]);
                    $angle = $d->get_angle();
                }                

                $textheight = $this->commentheight[$dir];
                $textlength = $this->commentlength[$dir];

                $centre_distance = $widths[$dir] + 4 + $nudgeout;

                if ($this->commentstyle == 'center') {
                    $centre_distance = $nudgeout - ($textheight / 2);
                }

                // find the normal to our link, so we can get outside the arrow               
                $flipped = false;
                $d->normalise();
                $n = $d->get_normal();

                // if the text will be upside-down, rotate it, flip it, and right-justify it
                // not quite as catchy as Missy's version
                if (abs($angle) > 90) {
                    $angle -= 180;

                    if ($angle < -180) {
                        $angle += 360;
                    }

                    $edge->AddVector($d, $nudgealong);
                    $edge->AddVector($n, -$centre_distance);
                    $flipped = true;
                } else {
                    $edge->AddVector($d, $nudgealong);
                    $edge->AddVector($n, $centre_distance);
                }

                if (!$flipped && ($extra + $textlength) > $totaldistance) {
                    $edge->AddVector($d, -$textlength);
                }

                if ($flipped && ($extra - $textlength) < 0) {
                    $edge->AddVector($d, $textlength);
                }

                $this->comment_x[$dir] = $edge->x;
                $this->comment_y[$dir] = $edge->y;
                $this->comment_angle[$dir] = $angle;
            }       
    }

    /**
     * Draw the comment text. By this stage, the actual position and angle
     * and the final text is already calculated.
     *
     * @param GDImageRef $image the image ref to draw into
     * @param GDColorRef $col[] colours for in and out text
     */
    function DrawComments($image, $col)
    {
        $dirs = $this->GetDirectionList();


        foreach ($dirs as $dir) {

            if ($this->commenttext[$dir] != '') {
                // FINALLY, draw the text!
                $this->owner->myimagestring($image, $this->commentfont,
                    $this->comment_x[$dir], $this->comment_y[$dir],
                    $this->commenttext[$dir], $col[$dir],
                    $this->comment_angle[$dir]);
            }
            
        }
    }

    function Draw($im, &$map)
    {
        // Get the positions of the end-points
        $x1 = $map->nodes[$this->a->name]->x;
        $y1 = $map->nodes[$this->a->name]->y;

        $x2 = $map->nodes[$this->b->name]->x;
        $y2 = $map->nodes[$this->b->name]->y;

        if (
			is_null($x1)
			|| is_null($y1)
			|| is_null($x2)
			|| is_null($y2)
			) {
            wm_warn("LINK " . $this->name . " uses a NODE with no POSITION! [WMWARN35]\n");
            return;
        }
		
        if (($this->linkstyle == 'twoway')
            && ($this->labeloffset_in < $this->labeloffset_out)
                && (intval($map->get_hint("nowarn_bwlabelpos")) == 0)) {
            wm_warn("LINK " . $this->name
                . " probably has it's BWLABELPOSs the wrong way around [WMWARN50]\n");
        }

        list($dx, $dy) = calc_offset($this->a_offset, $map->nodes[$this->a->name]->width,
            $map->nodes[$this->a->name]->height);
        $x1 += $dx;
        $y1 += $dy;

        list($dx, $dy) = calc_offset($this->b_offset, $map->nodes[$this->b->name]->width,
            $map->nodes[$this->b->name]->height);
        $x2 += $dx;
        $y2 += $dy;

        if (($x1 == $x2) && ($y1 == $y2) && sizeof($this->vialist) == 0) {
            wm_warn("Zero-length link " . $this->name . " skipped. [WMWARN45]");
            return;
        }

        $nvia = 0;
		
        $outlinecol = new WMColour($this->outlinecolour);
        $commentcol = new WMColour($this->commentfontcolour);

        $outline_colour = $outlinecol->gdallocate($im);

        $xpoints = array ();
        $ypoints = array ();

        $xpoints[] = $x1;
        $ypoints[] = $y1;

        # warn("There are VIAs.\n");
        foreach ($this->vialist as $via) {
            # imagearc($im, $via[0],$via[1],20,20,0,360,$map->selected);
            if (isset($via[2])) {
                $xpoints[] = $map->nodes[$via[2]]->x + $via[0];
                $ypoints[] = $map->nodes[$via[2]]->y + $via[1];
            } else {
                $xpoints[] = $via[0];
                $ypoints[] = $via[1];
            }
            $nvia++;
        }

        $xpoints[] = $x2;
        $ypoints[] = $y2;

        $link_in_colour = $this->colours[IN];
        $link_out_colour = $this->colours[OUT];

        $gd_in_colour = $link_in_colour->gdallocate($im);
        $gd_out_colour = $link_out_colour->gdallocate($im);

     
        $link_width = $this->width;
        // these will replace the one above, ultimately.
        $link_in_width = $this->width;
        $link_out_width = $this->width;
        // and these will replace those
        $this->calculatedwidths[IN] = $this->width;
        $this->calculatedwidths[OUT] = $this->width;

        // for bulging animations
        if (($map->widthmod) || ($map->get_hint('link_bulge') == 1)) {
            // a few 0.1s and +1s to fix div-by-zero, and invisible links
            $link_width = (($link_width * $this->inpercent * 1.5 + 0.1) / 100) + 1;
            // these too
            $link_in_width = (($link_in_width * $this->inpercent * 1.5 + 0.1) / 100) + 1;
            $link_out_width = (($link_out_width * $this->outpercent * 1.5 + 0.1) / 100)
                + 1;
            $this->calculatedwidths[IN] = (($this->calculatedwidths[IN] * $this->inpercent * 1.5 + 0.1) / 100) + 1;
            $this->calculatedwidths[OUT] = (($this->calculatedwidths[OUT] * $this->outpercent * 1.5 + 0.1) / 100)
                + 1;
        }

        // if there are no vias, we can skip a lot of curve-related calculations
		if($nvia == -1) {

                    $this->curvepoints = calc_straight($xpoints, $ypoints);

                    // if the link is straight, then some simple linear interpolation
                    // will find the positions for everything
                    $this->mid_x = linterp($x1, $x2, $this->splitpos);
                    $this->mid_y = linterp($y1, $y2, $this->splitpos);

                    $this->label_x[IN] = linterp($x1, $x2, $this->labeloffset_in);
                    $this->label_y[IN] = linterp($y1, $y2, $this->labeloffset_in);

                    $this->label_x[OUT] = linterp($x1, $x2, $this->labeloffset_out);
                    $this->label_y[OUT] = linterp($y1, $y2, $this->labeloffset_out);

                    # $angle = ($y2-$y1) / ($x2-$x1);
                    $angle = rad2deg(atan2($y1-$y2, $x2-$x1));

                    $this->label_angle[IN] = $angle;
                    $this->label_angle[OUT] = $angle;
                    $this->comment_angle[IN] = $angle;
                    $this->comment_angle[OUT] = $angle;

                    // these are ON the link axis, which isn't actually correct
                    $this->comment_x[IN] = linterp($x1,$x2,$this->commentoffset_in);
                    $this->comment_y[IN] = linterp($y1,$y2,$this->commentoffset_in);
                    $this->comment_x[OUT] = linterp($x1,$x2,$this->commentoffset_out);
                    $this->comment_y[OUT] = linterp($y1,$y2,$this->commentoffset_out);

                    if($this->linkstyle == 'oneway') {
                        DrawArrow($im, $x1,$y1, $x2, $y2,
                            $link_out_width,
                            $outline_colour, $gd_out_colour,
                            $map, $this->name, OUT);

                    } else {
                        DrawArrow($im, $x2,$y2, $this->mid_x, $this->mid_y,
                                $link_in_width,
                                $outline_colour, $gd_in_colour,
                                $map, $this->name, IN);
                        DrawArrow($im, $x1,$y1, $this->mid_x, $this->mid_y,
                            $link_out_width,
                            $outline_colour, $gd_out_colour,
                            $map, $this->name, OUT);
                    
                    }
                }
		else
		{
                    // from here, we're actually drawing a link with VIAs
                    // so there is a curvepoints array and all the rest
                    if ( ($this->viastyle == 'curved')) {
				// Calculate the spine points - the actual curve
				$this->curvepoints = calc_curve($xpoints, $ypoints);

				// then draw the curve itself
				draw_curve($im, $this->curvepoints, array (
					$link_in_width,
					$link_out_width
				), $outline_colour, array (
					$gd_in_colour,
					$gd_out_colour
				), $this->name, $map, $this->splitpos, ($this->linkstyle
					== 'oneway' ? true : false));
			}

			if ( ($this->viastyle == 'angled')) {
				// Calculate the spine points - the actual not a curve really, but we
				// need to create the array, and calculate the distance bits, otherwise
				// things like bwlabels won't know where to go.
					
				$this->curvepoints = calc_straight($xpoints, $ypoints);
				
				// then draw the "curve" itself
				draw_straight($im, $this->curvepoints, array (
					$link_in_width,
					$link_out_width
				), $outline_colour, array (
					$gd_in_colour,
					$gd_out_colour
				), $this->name, $map, $this->splitpos, ($this->linkstyle
					== 'oneway' ? true : false));
			}

                        // now work out where all the 'furniture' goes....

                        $curvelength = $this->curvepoints[count($this->curvepoints) - 1][2];
                        // figure out where the labels should be, and what the angle of the curve is at that point
                        list($this->label_x[OUT], $this->label_y[OUT], $junk, $this->label_angle[OUT]) =
                            find_distance_coords_angle($this->curvepoints, ($this->labeloffset_out / 100)
                                * $curvelength);
                        list( $this->label_x[IN],  $this->label_y[IN], $junk, $this->label_angle[IN]) =
                            find_distance_coords_angle($this->curvepoints, ($this->labeloffset_in / 100)
                                * $curvelength);
		}

                // at this stage, the actual link arrow(s) are drawn,
                // and the positions/angles for bwlabels are known

        // if the comment strings are both blank, or the colour is 'none', just
        // skip the comments altogether
        if (!$commentcol->is_none() && $this->comments[IN].$this->comments[OUT] != '') {

            // Precalculate text, and text positions
            $this->ProcessComments();

            $this->CalculateCommentPosition(array (
                $link_in_width * 1.1,
                $link_out_width * 1.1
            ));

        //    wm_draw_marker_cross($im,$map->selected, $this->comment_x[IN], $this->comment_y[IN]);
        //    wm_draw_marker_cross($im,$map->selected, $this->comment_x[OUT], $this->comment_y[OUT]);

            if ($commentcol->is_contrast()) {
                $commentcol_in = $link_in_colour->contrast();
                $commentcol_out = $link_out_colour->contrast();
            } else {
                $commentcol_in = $commentcol;
                $commentcol_out = $commentcol;
            }

            $comment_colour_in = $commentcol_in->gdallocate($im);
            $comment_colour_out = $commentcol_out->gdallocate($im);

            $this->DrawComments($im, array (
                $comment_colour_in,
                $comment_colour_out
            ));
        }
        
        if (!is_null($this->label_x[OUT])) {
            $outbound = array (
                $this->label_x[OUT],
                $this->label_y[OUT],
                0,
                0,
                $this->outpercent,
                $this->bandwidth_out,
                $this->label_angle[OUT],
                OUT
            );

            $inbound = array (
                $this->label_x[IN],
                $this->label_y[IN],
                0,
                0,
                $this->inpercent,
                $this->bandwidth_in,
                $this->label_angle[IN],
                IN
            );

            if ($map->sizedebug) {
                $outbound[5] = $this->max_bandwidth_out;
                $inbound[5] = $this->max_bandwidth_in;
            }

            if ($this->linkstyle == 'oneway') {
                $tasks = array ($outbound);
            } else {
                $tasks = array (
                    $inbound,
                    $outbound
                );
            }

            foreach ($tasks as $task) {
                $thelabel = "";

                $thelabel = $map->ProcessString($this->bwlabelformats[$task[7]], $this);

                if ($thelabel != '') {
                    wm_debug("Bandwidth for label is " . $task[5] . "\n");

                    $padding = intval($this->get_hint('bwlabel_padding'));

// if screenshot_mode is enabled, wipe any letters to X and wipe any IP address to 127.0.0.1
// hopefully that will preserve enough information to show cool stuff without leaking info
                    if ($map->get_hint('screenshot_mode') == 1) {
                        $thelabel = wm_screenshotify($thelabel);
                    }

                    if ($this->labelboxstyle == 'angled') {
                        $angle = $task[6];
                    } else {
                        $angle = 0;
                    }

                    $map->DrawLabelRotated($im, $task[0], $task[1], $angle, $thelabel,
                        $this->bwfont, $padding, $this->name, $this->bwfontcolour,
                        $this->bwboxcolour, $this->bwoutlinecolour, $map, $task[7]);

                }
            }
        }
    }

    function WriteConfig()
    {
        $output = '';

        if ($this->config_override != '') {
            $output = $this->config_override . "\n";
        } else {
            $dd = $this->owner->links[$this->template];

            wm_debug("Writing config for LINK $this->name against $this->template\n");

            $basic_params = array (
                array (
                    'width',
                    'WIDTH',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'zorder',
                    'ZORDER',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'overlibwidth',
                    'OVERLIBWIDTH',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'overlibheight',
                    'OVERLIBHEIGHT',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'arrowstyle',
                    'ARROWSTYLE',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'viastyle',
                    'VIASTYLE',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'linkstyle',
                    'LINKSTYLE',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'splitpos',
                    'SPLITPOS',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'duplex',
                    'DUPLEX',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'commentstyle',
                    'COMMENTSTYLE',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'labelboxstyle',
                    'BWSTYLE',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'bwfont',
                    'BWFONT',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'commentfont',
                    'COMMENTFONT',
                    CONFIG_TYPE_LITERAL
                ),
                array (
                    'bwoutlinecolour',
                    'BWOUTLINECOLOR',
                    CONFIG_TYPE_COLOR
                ),
                array (
                    'bwboxcolour',
                    'BWBOXCOLOR',
                    CONFIG_TYPE_COLOR
                ),
                array (
                    'outlinecolour',
                    'OUTLINECOLOR',
                    CONFIG_TYPE_COLOR
                ),
                array (
                    'commentfontcolour',
                    'COMMENTFONTCOLOR',
                    CONFIG_TYPE_COLOR
                ),
                array (
                    'bwfontcolour',
                    'BWFONTCOLOR',
                    CONFIG_TYPE_COLOR
                )
            );

            # TEMPLATE must come first. DEFAULT
            if ($this->template != 'DEFAULT' && $this->template != ':: DEFAULT ::') {
                $output .= "\tTEMPLATE " . $this->template . "\n";
            }

            foreach ($basic_params as $param) {
                $field = $param[0];
                $keyword = $param[1];

                # $output .= "# For $keyword: ".$this->$field." vs ".$dd->$field."\n";
                if ($this->$field != $dd->$field)
                #if (1==1)
                {
                    if ($param[2] == CONFIG_TYPE_COLOR)
                        $output .= "\t$keyword " . render_colour($this->$field) . "\n";

                    if ($param[2] == CONFIG_TYPE_LITERAL)
                        $output .= "\t$keyword " . $this->$field . "\n";
                }
            }

            $val = $this->usescale . " " . $this->scaletype;
            $comparison = $dd->usescale . " " . $dd->scaletype;

            if (($val != $comparison)) {
                $output .= "\tUSESCALE " . $val . "\n";
            }


            if ($this->infourl[IN] == $this->infourl[OUT]) {
                $dirs = array (
                    IN =>
                        ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
            } else {
                $dirs = array (
                    IN => "IN",
                    OUT => "OUT"
                ); // the full monty two-keyword version
            }

            foreach ($dirs as $dir => $tdir) {
                if ($this->infourl[$dir] != $dd->infourl[$dir]) {
                    $output .= "\t" . $tdir . "INFOURL " . $this->infourl[$dir] . "\n";
                }
            }

            if ($this->overlibcaption[IN] == $this->overlibcaption[OUT]) {
                $dirs = array (
                    IN =>
                        ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
            } else {
                $dirs = array (
                    IN => "IN",
                    OUT => "OUT"
                ); // the full monty two-keyword version
            }

            foreach ($dirs as $dir => $tdir) {
                if ($this->overlibcaption[$dir] != $dd->overlibcaption[$dir]) {
                    $output .= "\t" . $tdir . "OVERLIBCAPTION "
                        . $this->overlibcaption[$dir] . "\n";
                }
            }

            if ($this->notestext[IN] == $this->notestext[OUT]) {
                $dirs = array (
                    IN =>
                        ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
            } else {
                $dirs = array (
                    IN => "IN",
                    OUT => "OUT"
                ); // the full monty two-keyword version
            }

            foreach ($dirs as $dir => $tdir) {
                if ($this->notestext[$dir] != $dd->notestext[$dir]) {
                    $output .= "\t" . $tdir . "NOTES " . $this->notestext[$dir] . "\n";
                }
            }

            if ($this->overliburl[IN] == $this->overliburl[OUT]) {
                $dirs = array (
                    IN =>
                        ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
            } else {
                $dirs = array (
                    IN => "IN",
                    OUT => "OUT"
                ); // the full monty two-keyword version
            }

            foreach ($dirs as $dir => $tdir) {
                if ($this->overliburl[$dir] != $dd->overliburl[$dir]) {
                    $output .= "\t" . $tdir . "OVERLIBGRAPH "
                        . join(" ", $this->overliburl[$dir]) . "\n";
                }
            }

// if formats have been set, but they're just the longform of the built-in styles, set them back to the built-in styles
            if ($this->labelstyle == '--' && $this->bwlabelformats[IN] == FMT_PERC_IN
                && $this->bwlabelformats[OUT] == FMT_PERC_OUT) {
                $this->labelstyle = 'percent';
            }

            if ($this->labelstyle == '--' && $this->bwlabelformats[IN] == FMT_BITS_IN
                && $this->bwlabelformats[OUT] == FMT_BITS_OUT) {
                $this->labelstyle = 'bits';
            }

            if ($this->labelstyle == '--' && $this->bwlabelformats[IN] == FMT_UNFORM_IN
                && $this->bwlabelformats[OUT] == FMT_UNFORM_OUT) {
                $this->labelstyle = 'unformatted';
            }

            // if specific formats have been set, then the style will be '--'
            // if it isn't then use the named style
            if (($this->labelstyle != $dd->labelstyle) && ($this->labelstyle != '--')) {
                $output .= "\tBWLABEL " . $this->labelstyle . "\n";
            }

// if either IN or OUT field changes, then both must be written because a regular BWLABEL can't do it
// XXX this looks wrong
            $comparison = $dd->bwlabelformats[IN];
            $comparison2 = $dd->bwlabelformats[OUT];

            if (($this->labelstyle == '--') && (($this->bwlabelformats[IN] != $comparison)
                || ($this->bwlabelformats[OUT] != '--'))) {
                $output .= "\tINBWFORMAT " . $this->bwlabelformats[IN] . "\n";
                $output .= "\tOUTBWFORMAT " . $this->bwlabelformats[OUT] . "\n";
            }

            $comparison = $dd->labeloffset_in;
            $comparison2 = $dd->labeloffset_out;

            if (($this->labeloffset_in != $comparison)
                || ($this->labeloffset_out != $comparison2)) {
                $output .= "\tBWLABELPOS " . $this->labeloffset_in . " "
                    . $this->labeloffset_out . "\n";
            }

            $comparison = $dd->commentoffset_in . ":" . $dd->commentoffset_out;
            $mine = $this->commentoffset_in . ":" . $this->commentoffset_out;

            if ($mine != $comparison) {
                $output .= "\tCOMMENTPOS " . $this->commentoffset_in . " "
                    . $this->commentoffset_out . "\n";
            }

            $comparison = $dd->targets;

            if ($this->targets != $comparison) {
                $output .= "\tTARGET";

                foreach ($this->targets as $target) {
                    if (strpos($target[4], " ") == false) {
                        $output .= ' ' . $target[4];
                    } else {
                        $output .= ' "' . $target[4] . '"';
                    }
                }
                $output .= "\n";
            }

            foreach (array (
                IN,
                OUT
            ) as $dir) {
                if ($dir == IN) {
                    $tdir = 'IN';
                }

                if ($dir == OUT) {
                    $tdir = 'OUT';
                }

                $comparison = $dd->comments[$dir];

                if ($this->comments[$dir] != $comparison) {
                    $output .= "\t" . $tdir . "COMMENT " . $this->comments[$dir] . "\n";
                }
            }

            if (isset($this->a) && isset($this->b)) {
                $output .= "\tNODES " . $this->a->name;

                if ($this->a_offset != 'C') {
                    $output .= ":" . $this->a_offset;
                }

                $output .= " " . $this->b->name;

                if ($this->b_offset != 'C') {
                    $output .= ":" . $this->b_offset;
                }

                $output .= "\n";
            }

            if (count($this->vialist) > 0) {
                foreach ($this->vialist as $via) {
                    if (isset($via[2])) {
                        $output .= sprintf("\tVIA %s %d %d\n", $via[2], $via[0], $via[1]);
                    } else {
                        $output .= sprintf("\tVIA %d %d\n", $via[0], $via[1]);
                    }
                }
            }

            if (($this->max_bandwidth_in != $dd->max_bandwidth_in)
                || ($this->max_bandwidth_out != $dd->max_bandwidth_out)
                    || ($this->name == 'DEFAULT')) {
                if ($this->max_bandwidth_in == $this->max_bandwidth_out) {
                    $output .= "\tBANDWIDTH " . $this->max_bandwidth_in_cfg . "\n";
                } else {
                    $output .= "\tBANDWIDTH " . $this->max_bandwidth_in_cfg . " "
                        . $this->max_bandwidth_out_cfg . "\n";
                }
            }

            foreach ($this->hints as $hintname => $hint) {
                // all hints for DEFAULT node are for writing
                // only changed ones, or unique ones, otherwise
                if (($this->name == 'DEFAULT')
                    || (isset($dd->hints[$hintname]) && $dd->hints[$hintname] != $hint)
                        || (!isset($dd->hints[$hintname]))) {
                    $output .= "\tSET $hintname $hint\n";
                }
            }

            if ($output != '') {
                $output = "LINK " . $this->name . "\n" . $output . "\n";
            }
        }
        return ($output);
    }

    function asJS()
    {
        $js = '';
        $js .= "Links[" . js_escape($this->name) . "] = {";
        $js .= "\"id\":" . $this->id . ", ";

        if (isset($this->a)) {
            $js .= "a:'" . $this->a->name . "', ";
            $js .= "b:'" . $this->b->name . "', ";
        }

        $js .= "width:'" . $this->width . "', ";
        $js .= "target:";

        $tgt = '';

        foreach ($this->targets as $target) {
            if (strpos($target[4], " ") == false) {
                $tgt .= $target[4] . ' ';
            } else {
                $tgt .= '"' . $target[4] . '" ';
            }
        }

        $js .= js_escape(trim($tgt));
        $js .= ",";

        $js .= "bw_in:" . js_escape($this->max_bandwidth_in_cfg) . ", ";
        $js .= "bw_out:" . js_escape($this->max_bandwidth_out_cfg) . ", ";

        $js .= "name:" . js_escape($this->name) . ", ";
        $js .= "overlibwidth:'" . $this->overlibheight . "', ";
        $js .= "overlibheight:'" . $this->overlibwidth . "', ";
        $js .= "overlibcaption:" . js_escape($this->overlibcaption[IN]) . ", ";

        $js .= "commentin:" . js_escape($this->comments[IN]) . ", ";
        $js .= "commentposin:" . intval($this->commentoffset_in) . ", ";

        $js .= "commentout:" . js_escape($this->comments[OUT]) . ", ";
        $js .= "commentposout:" . intval($this->commentoffset_out) . ", ";

        $js .= "infourl:" . js_escape($this->infourl[IN]) . ", ";
        $js .= "overliburl:" . js_escape(join(" ", $this->overliburl[IN]));

        $js .= "};\n";
        $js .= "LinkIDs[\"L" . $this->id . "\"] = " . js_escape($this->name) . ";\n";
        return $js;
    }

    function asJSON($complete = true)
    {
        $js = '';
        $js .= js_escape($this->name) . ': {';
        $js .= 'id":' . $this->id . ', ';

        if (isset($this->a)) {
            $js .= '"a":"' . $this->a->name . '", ';
            $js .= '"b":"' . $this->b->name . '", ';
        }

        if ($complete) {
            $js .= "\"infourl\":" . js_escape($this->infourl) . ", ";
            $js .= "\"overliburl\":" . js_escape($this->overliburl) . ", ";
            $js .= "\"width\":\"" . $this->width . "\", ";
            $js .= '"target":';

            $tgt = '';

            foreach ($this->targets as $target) {
                $tgt .= $target[4] . " ";
            }

            $js .= js_escape(trim($tgt));
            $js .= ",";

            $js .= "\"bw_in\":" . js_escape($this->max_bandwidth_in_cfg) . ", ";
            $js .= "\"bw_out\":" . js_escape($this->max_bandwidth_out_cfg) . ", ";

            $js .= "\"name\":" . js_escape($this->name) . ", ";
            $js .= "\"overlibwidth\":\"" . $this->overlibheight . "\", ";
            $js .= "\"overlibheight\":\"" . $this->overlibwidth . "\", ";
            $js .= "\"overlibcaption\":" . js_escape($this->overlibcaption) . ", ";
        }
        $vias = '"via": [';

        foreach ($this->vialist as $via) $vias .= sprintf("[%d,%d,'%s'],", $via[0],
            $via[1], $via[2]);
        $vias .= '],';
        $vias = str_replace('],],', ']]', $vias);
        $vias = str_replace('[],', '[]', $vias);
        $js .= $vias;

        $js .= "},\n";
        return $js;
    }
}
;

// vim:ts=4:sw=4:
?>
