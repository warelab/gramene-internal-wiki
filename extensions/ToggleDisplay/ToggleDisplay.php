<?php
 
$wgExtensionCredits['parserhook'][] = array(
  'name' => 'ToggleDisplay',
  'version' => ExtToggleDisplay::VERSION,
  'author' => '[http://www.mediawiki.org/wiki/User:RV1971 RV1971]',
  'url' => 'http://www.mediawiki.org/wiki/Extension:ToggleDisplay',
  'description' => 'show and hide regions on a page with a javascript click'
);
 
// instance of this extension class
$wgExtToggleDisplay = new ExtToggleDisplay();
 
// register the extension
$wgExtensionFunctions[] = array( &$wgExtToggleDisplay, 'setup' );
 
$wgHooks['BeforePageDisplay'][] = array( &$wgExtToggleDisplay, 
                    'onBeforePageDisplay' );
 
class ExtToggleDisplay
{
  const VERSION = '0.121';
 
  public static $mDefaultArgs = 
  array( 'status' => 'hide',
     'showtext' => '[show details]',
     'hidetext' => '[hide details]',
     'linkstyle' => 'font-size:smaller' );
 
  private static $mCount = 0;
 
  public function setup() 
  { 
    global $wgParser;
 
    // XML-style extension
    $wgParser->setHook( 'toggledisplay', array( &$this, 'toggleDisplay' ) );
  }
 
  function onBeforePageDisplay( &$outarray )
  {
    global $wgStylePath;
 
    $outarray->addScript( '<script type="text/javascript"> 
function toggleDisplay( id, hidetext, showtext )
{
  link = document.getElementById( id + "l" ).childNodes[0];
 
  with( document.getElementById( id ).style )
    {
      if( display == "none" )
    {
      display = "inline";
      link.nodeValue = hidetext;
    }
      else
    {
      display = "none";
      link.nodeValue = showtext;
    }
    }
}
</script>' );
 
    return true;
  }
 
  function toggleDisplay( $input, $args, &$parser )
  {
    self::$mCount++;
 
    $id = 'toggledisplay' . self::$mCount;
    $linkid = $id . 'l';
 
    extract( array_merge( self::$mDefaultArgs, $args ) );
    $hidetext = htmlspecialchars( $hidetext );
    $showtext = htmlspecialchars( $showtext );
 
    if( $status == 'hide' )
      {
    $display = 'none';
    $linktext = $showtext;
      }
    else
      {
    $display = 'inline';
    $linktext = $hidetext;
      }
 
    $result = <<<EOD
<a id='$linkid' href='javascript:toggleDisplay( "$id", "$hidetext", "$showtext" )' style='$linkstyle'>$linktext</a><div id='$id' style='display:$display;'>
EOD;
 
    $result .= $parser->recursiveTagParse( $input )
      . '</div>';
 
    return $result;
  }
}
?>
