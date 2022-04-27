<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'cbe_frontauth';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.9.8';
$plugin['author'] = 'Claire Brione';
$plugin['author_uri'] = 'http://www.clairebrione.com/';
$plugin['description'] = 'Manage backend connections from frontend';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '4';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '0';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

/**************************************************
 **
 ** Register front-end tags
 **
 **************************************************/

if (class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('cbe_frontauth')
        ->register('cbe_frontauth_backend')
        ->register('cbe_frontauth_box')
        ->register('cbe_frontauth_if_connected')
        ->register('cbe_frontauth_invite')
        ->register('cbe_frontauth_edit_article')
        ->register('cbe_frontauth_label')
        ->register('cbe_frontauth_link')
        ->register('cbe_frontauth_if_logged')
        ->register('cbe_frontauth_login')
        ->register('cbe_frontauth_loginwith')
        ->register('cbe_frontauth_logname')
        ->register('cbe_frontauth_logout')
        ->register('cbe_frontauth_password')
        ->register('cbe_frontauth_protect')
        ->register('cbe_frontauth_redirect')
        ->register('cbe_frontauth_reset')
        ->register('cbe_frontauth_stay')
        ->register('cbe_frontauth_submit')
        ->register('cbe_frontauth_whois');
}

/**************************************************
 **
 ** Local language strings, possible customisation here
 **
 **************************************************/
function _cbe_fa_lang()
{
    return( array( 'login_failed'         => "Login failed"
                 , 'login_to_textpattern' => gTxt( 'login_to_textpattern' )
                 , 'name'                 => gTxt( 'name' )
                 , 'password'             => gTxt( 'password' )
                 , 'log_in_button'        => gTxt( 'log_in_button' )
                 , 'stay_logged_in'       => gTxt( 'stay_logged_in' )
                 , 'logout'               => gTxt( 'logout' )
                 , 'edit'                 => gTxt( 'edit' )
                 , 'change_password'      => gTxt( 'change_password' )
                 , 'password_reset'       => gTxt( 'password_reset' )
                 )
          ) ;
}
/**************************************************
 **
 ** Don't edit further
 **
 **************************************************/

/**************************************************
 **
 ** Available tags
 **
 **************************************************/

/* == Shortcuts for cbe_frontauth() == */

// -- Global init for redirection after login and/or logout
// -------------------------------------------------------------------
function cbe_frontauth_redirect( $atts )
{
    return( _cbe_fa_init( $atts, 'redir' ) ) ;
}

// -- Global init for login/logout invites
// -------------------------------------------------------------------
function cbe_frontauth_invite( $atts )
{
    return( _cbe_fa_init( $atts, 'invite' ) ) ;
}

// -- Global init for login/logout buttons/link labels
// -------------------------------------------------------------------
function cbe_frontauth_label( $atts )
{
    return( _cbe_fa_init( $atts, 'label' ) ) ;
}

// -- Global init for login with user name, email, or automatic detection
// -------------------------------------------------------------------
function cbe_frontauth_loginwith( $atts )
{
    return( _cbe_fa_init( $atts, 'with' ) ) ;
}

// -- Login / Logout box
// -------------------------------------------------------------------
function cbe_frontauth_box( $atts, $thing = '' )
{
    $public_atts = lAtts( array( 'login_invite'  => _cbe_fa_gTxt( 'login_to_textpattern' )
                               , 'logout_invite' => ''
                               , 'show_change'   => '1'
                               , 'show_reset'    => '1'
                               , 'tag_invite'    => ''
                               , 'login_label'   => _cbe_fa_gTxt( 'log_in_button' )
                               , 'logout_label'  => _cbe_fa_gTxt( 'logout' )
                               , 'logout_type'   => 'button'
                               , 'tag_error'     => 'span'
                               , 'class_error'   => 'cbe_fa_error'
                               )
                           + _cbe_fa_format()
                         , $atts ) ;

    return( cbe_frontauth( $public_atts
                          , $thing ? $thing : '<p><txp:text item="logged_in_as" /> <txp:cbe_frontauth_whois wraptag="span" class="user"/></p>'
                          ) ) ;

}

// -- Standalone login form
// -------------------------------------------------------------------
function cbe_frontauth_login( $atts, $thing = '' )
{
    return( _cbe_fa_inout_process( 'login', $atts, $thing ) ) ;
}

// -- Standalone logout form / link
// -------------------------------------------------------------------
function cbe_frontauth_logout( $atts, $thing = '' )
{
    return( _cbe_fa_inout_process( 'logout', $atts, $thing ) ) ;
}

// -- Protect parts from non-connected viewers
// -------------------------------------------------------------------
function cbe_frontauth_protect( $atts, $thing )
{
    $public_atts = lAtts( array( 'link'      => ''
                               , 'linklabel' => ''
                               , 'target'    => '_self'
                               , 'name'      => ''
                               , 'level'     => ''
                               )
                         + _cbe_fa_format()
                         , $atts ) ;

    if( $public_atts['target'] == '_get' )
        $public_atts['target'] = '_self' ;

    return( cbe_frontauth( array( 'login_invite' => '' , 'logout_invite' => ''
                                , 'show_login'   => '0', 'show_logout'   => '0'
                                , 'show_reset'   => '0', 'show_change'   => '0' ) + $public_atts
                         , $thing
                         ) ) ;
}
function cbe_frontauth_if_logged( $atts, $thing )
{
    return( cbe_frontauth_protect( $atts, $thing ) ) ;
}
function cbe_frontauth_if_connected( $atts, $thing )
{
    return( cbe_frontauth_protect( $atts, $thing ) ) ;
}

/* == Elements == */

// -- Generates input field for name
// -------------------------------------------------------------------
function cbe_frontauth_logname( $atts, $defvalue=null )
{
    return( _cbe_fa_identity( 'name', $atts, $defvalue ) ) ;
}

// -- Generates input field for password
// -------------------------------------------------------------------
function cbe_frontauth_password( $atts, $defvalue=null )
{
    return( _cbe_fa_identity( 'password', $atts, $defvalue ) ) ;
}

// -- Generates checkbox for stay (connected on this browser)
// -------------------------------------------------------------------
function cbe_frontauth_stay( $atts )
{
    extract( lAtts( array ( 'label' => _cbe_fa_gTxt( 'stay_logged_in' )
                          )
                  + _cbe_fa_format()
                  , $atts
                  )
           ) ;

    $out  = checkbox('p_stay', 1, cs('txp_login'), '', 'stay') ;
    $out .= '<label for="stay">'.$label.'</label>' ;
    return( doTag( $out, $wraptag, $class ) ) ;
}

// -- Generates submit button
// -------------------------------------------------------------------
function cbe_frontauth_submit( $atts )
{
    $public_atts = lAtts( array ( 'label' => ''
                                , 'type'  => 'login'
                                )
                        + _cbe_fa_format()
                        , $atts
                        ) ;

    return( _cbe_fa_button( $public_atts ) ) ;
}

// -- Displays connected user's informations
// -------------------------------------------------------------------
function cbe_frontauth_whois( $atts )
{
    extract( lAtts( array ( 'type'       => 'name'
                          , 'format'     => ''
                          )
                    + _cbe_fa_format()
                  , $atts
                  )
           ) ;

    $types = do_list( $type ) ;
    $whois = cbe_frontauth( array( 'init' => '0', 'value' => $types ) ) ;

    if( isset( $whois['last_access'] ) )
    {
        global $dateformat ;
        $whois['last_access'] = safe_strftime( $format ? $format : $dateformat, strtotime( $whois['last_access'] ) ) ;
    }

    return( doWrap( $whois, $wraptag, $break, $class, $breakclass ) ) ;
}

/* == Off-topic, but useful == */

// -- Generates a link, normal or with a GET parameter
// -------------------------------------------------------------------
function cbe_frontauth_link( $atts )
{   // $class applies to anchor if no $wraptag supplied
    extract( lAtts( array ( 'label'  => ''
                          , 'link'   => ''
                          , 'target' => '_self'
                          )
                  + _cbe_fa_format()
                  , $atts
                  )
           ) ;

    $link = doStripTags( $link ) ;
    $out = _cbe_fa_link( compact( 'link', 'target' ) ) ;
    $out = href( $label, $out
               , (($target !== '_get')  ? ' target="'.$target.'"' : '')
               . ((!$wraptag && $class) ? ' class="'.$class.'"'   : '') ) ;
    return( doTag( $out, $wraptag, $class ) ) ;
}

// -- Returns path to textpattern backend
// -------------------------------------------------------------------
function cbe_frontauth_backend()
{
//            . substr(strrchr(txpath, "/"), 1)
    return( preg_replace('|//$|','/', rhu.'/')
            . substr(strrchr(txpath, DS), 1)
            . '/index.php'
           ) ;
}

// -- Returns button (standalone) or link to edit current article
// -------------------------------------------------------------------
function cbe_frontauth_edit_article( $atts )
{
    global $thisarticle ;
    assert_article() ;

    extract( lAtts( array ( 'label' => _cbe_fa_gTxt( 'edit' )
                          , 'type'  => 'button'
                          )
                  + _cbe_fa_format()
                  , $atts
                  )
           ) ;

    $path_parts = array( 'event'      => 'article'
                       , 'step'       => 'edit'
                       , 'ID'         => $thisarticle['thisid']
                       ) ;

    if( $type == 'button' )
    {
        $out = array() ;

        foreach( $path_parts as $part => $value )
            $out[] = hInput( $part, $value ) ;

        $out[] = cbe_frontauth_submit( array( 'label' => $label, 'type' => '', 'class' =>'publish' ) ) ;

         return( _cbe_fa_form( array( 'statements' => join( n, $out ) ) ) ) ;
    }
    elseif( $type == 'link' )
    {
        $path_parts[ '_txp_token' ] = form_token() ;
        array_walk(
            $path_parts,
            function(&$v,$k) { $v = $k."=".$v; }
        );
        $link = cbe_frontauth_backend() . '?' . join( '&', $path_parts ) ;

        return( cbe_frontauth_link( compact( 'link', 'label'
                                           , array_keys( _cbe_fa_format() )
                                           )
                                  )
              ) ;
    }
    else
        return ;
}

/**************************************************
 **
 ** Utilities (kinda private functions)
 **
 **************************************************/

// -- Gets and returns local lang strings (txp admin + plugin specifics)
// -------------------------------------------------------------------
function _cbe_fa_gTxt( $text, $atts = array() )
{
    static $aTexts = array() ;
    if( ! $aTexts )
        $aTexts = _cbe_fa_lang() ;

    return( isset( $aTexts[ $text ] ) ? strtr( $aTexts[ $text ], $atts ) : gTxt( $text ) ) ;
}

// -- Common presentational attributes
// -------------------------------------------------------------------
function _cbe_fa_format()
{
    return( array( 'wraptag'    => ''
                 , 'class'      => ''
                 , 'break'      => ''
                 , 'breakclass' => ''
                 )
          ) ;
}

// -- Global initialisations (redirect, invite, label, loginwith)
// -------------------------------------------------------------------
function _cbe_fa_init( $atts, $type )
{
    extract( lAtts( array ( 'for' => '', 'value' => '' ), $atts ) ) ;
    if( $for === '' )
        $for = 'login' ;
    $init_for = do_list( $for ) ;

    if( ($index=array_search( 'logged', $init_for )) !== false )
        $init_for[ $index ] = 'logout' ;

    array_walk(
        $init_for,
        function(&$v,$k,$p) { $v = $v."_".$p; },
        $type
    );

    if( ($init_list = @array_combine( $init_for, do_list( $value ) )) === false )
        return ;

    cbe_frontauth( array( 'init' => '1' ) + $init_list ) ;
    return ;
}

// -- Retrieve user's info, if connected
// -- textpattern/lib/txp_misc.php - is_logged_in() as a starting point
// -------------------------------------------------------------------
function _cbe_fa_logged_in( &$user, $txp_user = null )
{
    if( $txp_user !== null )
        $name = $txp_user ;
    elseif( !($name = substr(cs('txp_login_public'), 10)) )
    {
        $user[ 'name' ] = false ;
        return( false ) ;
    }

    $rs = safe_row('nonce, name, RealName, email, privs, last_access', 'txp_users', "name = '".doSlash($name)."'");

    if( $rs && ($txp_user !== null || substr(md5($rs['nonce']), -10) === substr(cs('txp_login_public'), 0, 10) ) )
    {
        unset( $rs[ 'nonce' ] ) ;
        $user = $rs ;
        return( true ) ;
    }
    else
    {
        $user[ 'name' ] = false ;
        return( false ) ;
    }
}

// -- Checks current user against required privileges
// -- Thanks to Ruud Van Melick's rvm_privileged (http://vanmelick.com/txp/)
// -------------------------------------------------------------------
function _cbe_fa_privileged( $r_name, $r_level, $u_name, $u_level )
{
    $chk_name  = !$r_name  || in_array( $u_name , do_list( $r_name  ) ) ;
    $chk_level = !$r_level || in_array( $u_level, do_list( $r_level ) ) ;
    return( $chk_name || $chk_level ) ;
}

// -- Generates input field for name or password
// -------------------------------------------------------------------
function _cbe_fa_identity( $field, $atts, $value=null )
{
    extract( lAtts( array ( 'label'     => _cbe_fa_gTxt( $field )
                          , 'label_sfx' => ''
                          )
                  + _cbe_fa_format()
                  , $atts
                  )
           ) ;

    $$field = '' ;
    if( $field == 'name' && cs('cbe_frontauth_login') != '' )
        list($name) = explode( ',', cs('cbe_frontauth_login') ) ;

    $out = array() ;
    $out[] = '<label for="'.$field.$label_sfx.'">'.$label.'</label>' ;
    $out[] =  fInput( ($field == 'name')    ? 'text'     : 'password'
                    ,(($field == 'name')    ? 'p_userid' : 'p_password') . $label_sfx
                    , ($field == 'name')    ? $name      : ($value !== null ? $value : '')
                    , (!$wraptag && $class) ? $class     : ''
                    , '', '', '', '', $field.$label_sfx ) ;

    return( doWrap( $out, $wraptag, $break, $class, $breakclass ) ) ;
}

// -- Prepare call to cbe_frontauth() for login/logout form/link
// -------------------------------------------------------------------
function _cbe_fa_inout_process( $inout, $atts, $thing = '' )
{
    $plus_atts = ($inout == 'logout' )
               ? array( 'type'        => 'button'
                      , 'show_change' => '1'      )
               : array( 'show_stay'   => '0'
                      , 'show_reset'  => '1'      ) ;
    $public_atts = lAtts( array ( 'invite'     => _cbe_fa_gTxt( ($inout == 'login') ? 'login_to_textpattern'
                                                                                    : '' )
                                , 'tag_invite' => ''
                                , 'label'      => _cbe_fa_gTxt( ($inout == 'login') ? 'log_in_button'
                                                                                    : 'logout' )
                                , 'form'       => ''
                                , 'tag_error'  => 'span', 'class_error' => 'cbe_fa_error'
                                )
                        + $plus_atts + _cbe_fa_format()
                        , $atts ) ;

    if( isset( $public_atts['invite'] ) )
    {
        $public_atts[$inout.'_invite'] = $public_atts['invite'] ;
        unset( $public_atts['invite'] ) ;
    }
    if( isset( $public_atts['label'] ) )
    {
        $public_atts[$inout.'_label'] = $public_atts['label'] ;
        unset( $public_atts['label'] ) ;
    }
    if( isset( $public_atts['form'] ) )
    {
        $public_atts[$inout.'_form'] = $public_atts['form'] ;
        unset( $public_atts['form'] ) ;
    }
    if( isset( $public_atts['type'] ) )
    {
        $public_atts[$inout.'_type'] = $public_atts['type'] ;
        unset( $public_atts['type'] ) ;
    }
    if( $thing )
        $public_atts[$inout.'_form'] = $thing ;

    $show = ($inout == 'login') ? 'logout' : 'login' ;
    return( cbe_frontauth( array( 'show_'.$show => '0' ) + $public_atts ) ) ;
}

// -- Encloses statements in a submit form
// -------------------------------------------------------------------
function _cbe_fa_form( $atts )
{
    extract( lAtts( array( 'statements' => ''
                         , 'action'     => cbe_frontauth_backend()
                         , 'method'     => 'post'
                         )
                  , $atts
                  )
           ) ;

    if( ! $statements )
        return ;

    return( '<form action="'.$action.'" method="'.$method.'">'
            .n. $statements
            .n. '</form>' ) ;
}

// -- Generates a button (primary purpose : login/logout button)
// -- Extended to 'edit' (just in case) - 0.7
// -- Note: providing a label and setting type to blank works too
// -------------------------------------------------------------------
function _cbe_fa_button( $atts )
{
    extract( $atts ) ; // 'label', 'type', 'wraptag', class'

    if( ! $label and ! ($label = cbe_frontauth( array( 'init' => '0', 'value' => $type.'_label' ) )) )
        $label = _cbe_fa_gTxt( ($type == 'logout' || $type == 'edit' ) ? $type : 'log_in_button' ) ;

    $out = fInput( 'submit', '', $label, (!$wraptag && $class) ? $class : '' ) ;

    if( $type == 'logout' )
        $out .= hInput( 'p_logout', '1' ) ;
    elseif( $type == 'edit' )
        $out .= tInput() ;

    return( doTag( $out, $wraptag, $class ) ) ;
}

// -- Generates a link (primary purpose : logout link)
// -------------------------------------------------------------------
function _cbe_fa_link( $atts )
{
    extract( $atts ) ; // 'link', 'target'

    if( $target == '_get' )
    {
        $uri = serverSet( 'REQUEST_URI'  ) ;
        $qus = serverSet( 'QUERY_STRING' ) ;

        $len_uri = strlen( $uri ) ;
        $len_qus = strlen( $qus ) ;

        $uri = ($len_qus > 0) ? substr( $uri, 0, $len_uri-$len_qus-1 ) : $uri ;
        $qus = $qus . ($len_qus > 0 ? '&' : '') . $link ;

        $out = (substr( $uri, -1 ) !== '?' ) ? ($uri.'?'.$qus) : ($uri.$qus) ;
    }
    else
    {
        $out = $link ;
    }

    return( $out ) ;
}

// -- Generates login/logout form or logout link
// -------------------------------------------------------------------
function _cbe_fa_inout( $atts )
{
    extract( $atts ) ;

    $out = array() ;

    if( $form )
        $out[] = ($f=@fetch_form( $form )) ? parse( $f ) : parse( $form ) ; // label takes precedence here
    else
    {
        if( isset( $show_stay ) )
        {   // login

            $out[] = cbe_frontauth_logname(  array( 'class' => 'edit')
                                          +  compact( 'break', 'breakclass' ) ) ;
            $out[] = cbe_frontauth_password( array( 'class' => 'edit')
                                           + compact( 'break', 'breakclass' ) ) ;
            if( $show_stay )
                $out[] = cbe_frontauth_stay( array() ) ;

            $out[] = cbe_frontauth_submit( array( 'label' => $label, 'class' => 'publish' ) ) ;

        }
        else
        {   // logout
            $out[] = ($type == 'button')
                   ? cbe_frontauth_submit( array( 'label' => $label, 'type' => 'logout'
                                                , 'class' => $class ? $class : 'publish' ) )
                   : cbe_frontauth_link( array( 'label' => $label, 'link' => 'logout=1', 'target' => '_get'
                                              , 'class' => $class ? $class : 'publish' ) ) ;
        }
    }

//    $out = join( n, $out ) ;
    $out = doWrap( $out, $wraptag, $break, '', $breakclass ) ;
    return( (isset( $type ) && $type=='link')
            ? $out
            : _cbe_fa_form( array( 'statements' => $out, 'action' => page_url( array() ) ) ) ) ;
}

/* == Backbone == */

// -- Cookie mechanism - from textpattern/include/txp_auth.php - doTxpValidate()
// -------------------------------------------------------------------
function _cbe_fa_auth( $redir, $p_logout, $p_userid='', $p_password='', $p_stay='' )
{
    defined('LOGIN_COOKIE_HTTP_ONLY') || define('LOGIN_COOKIE_HTTP_ONLY', true);
    $hash  = md5(uniqid(mt_rand(), TRUE));
    $nonce = md5($p_userid.pack('H*',$hash));
    $pub_path = preg_replace('|//$|','/', rhu.'/') ;
    $adm_path = $pub_path . substr(strrchr(txpath, DS), 1) . '/' ;

    if( $p_logout )
    {
        $log_name = false ;

        safe_update( 'txp_users'
                   , "nonce = '".doSlash($hash)."'"
                   , "name = '".doSlash($p_userid)."'"
                   ) ;

        setcookie( 'txp_login'
                 , ''
                 , time()-3600
                 , $adm_path
                 ) ;

        setcookie( 'txp_login_public'
                 , ''
                 , time()-3600
                 , $pub_path
                 ) ;

        setcookie( 'cbe_frontauth_login'
                 , ''
                 , time()-3600
                 , $pub_path
                 ) ;
    }
//    elseif( ($log_name = txp_validate( $p_userid, $p_password, false )) !== false )
    elseif( ($log_name = txp_validate( $p_userid, $p_password )) !== false )
    {
        safe_update( 'txp_users'
                   , "nonce = '".doSlash($nonce)."'"
                   , "name = '".doSlash($p_userid)."'"
                   ) ;

        setcookie( 'txp_login'
                 , $p_userid.','.$hash
                 , ($p_stay ? time()+3600*24*365 : 0)
                 , $adm_path
                 , null
                 , null
                 , LOGIN_COOKIE_HTTP_ONLY
                 ) ;

        setcookie( 'txp_login_public'
                 , substr(md5($nonce), -10).$p_userid
                 , ($p_stay ? time()+3600*24*30 : 0)
                 , $pub_path
                 ) ;

        if( $p_stay )
            setcookie( 'cbe_frontauth_login'
                     , $p_userid.','.$hash
                     , time()+3600*24*365
                     , $pub_path
                     ) ;
    }

    if( $redir && ( $p_logout || $log_name !== false ) )
    {
        header( "Location:$redir" ) ;
        exit ;
    }

    return( $log_name ) ;
}

// -- Get the job done
// -------------------------------------------------------------------
function cbe_frontauth( $atts, $thing = null )
{
    include_once( txpath.'/lib/txplib_admin.php' ) ;
    include_once( txpath.'/include/txp_auth.php' ) ;
    global $txp_user ;
    static $inits = array( 'login_invite' => '' , 'logout_invite' => '' , 'tag_invite' => ''
                         , 'login_label'  => '' , 'logout_label'  => ''
                         , 'login_redir'  => '' , 'logout_redir'  => ''
                         , 'login_with'   => ''
                         ) ;
    static $cbe_fa_user = array( 'name'  => false , 'RealName'    => '' , 'email' => ''
                               , 'privs' => ''    , 'last_access' => ''
                               ) ;

    if( isset( $atts['init'] ) )
    {
        if( $atts['init'] )
        {
            unset( $atts['init'] ) ;

            foreach( $atts as $param => $value )
                $inits[$param] = $value ;

            return ;
        }
        else
        {
            if( is_array( $atts[ 'value' ] ) )
            {
                $whois = array() ;
                if( ! $cbe_fa_user[ 'name' ] ) _cbe_fa_logged_in( $cbe_fa_user ) ;
                foreach( $atts[ 'value' ] as $type )
                    $whois[ $type ] = $cbe_fa_user[ $type ] ;

                return( $whois ) ;
            }
            else
                return( isset( $inits[ $atts[ 'value' ] ] ) ? $inits[ $atts[ 'value' ] ] : '' ) ;
        }
    }

    $def_atts = array( 'form'          => ''
                     , 'tag_invite'    => ''
                     , 'show_login'    => '1'
                     , 'login_invite'  => _cbe_fa_gTxt( 'login_to_textpattern' )
                     , 'login_form'    => ''
                     , 'login_label'   => _cbe_fa_gTxt( 'log_in_button' )
                     , 'login_with'    => 'auto'
                     , 'login_redir'   => ''
                     , 'show_logout'   => '1'
                     , 'logout_invite' => ''
                     , 'logout_form'   => ''
                     , 'logout_label'  => _cbe_fa_gTxt( 'logout' )
                     , 'logout_type'   => 'button'
                     , 'logout_redir'  => ''
                     , 'show_stay'     => '0'
                     , 'show_reset'    => '1'
                     , 'show_change'   => '1'
                     , 'link'          => ''
                     , 'linklabel'     => ''
                     , 'target'        => '_self'
                     , 'name'          => ''
                     , 'level'         => ''
                     , 'tag_error'     => ''
                     , 'class_error'   => ''
                     ) ;

    $ini_atts = array() ;
    foreach( $inits as $param => $value )
    {   /* Inits take precedence on default values */
        if( !isset( $atts[$param] ) || $atts[$param] === $def_atts[$param] )
            $ini_atts[$param] = $value ;
    }

    extract( lAtts( $def_atts + _cbe_fa_format(), array_merge( $atts, array_filter( $ini_atts ) ) ) ) ;

    extract( psa( array( 'p_userid', 'p_password', 'p_stay', 'p_reset', 'p_logout', 'p_change' ) ) ) ;
    $logout = gps( 'logout' ) ;
    $p_logout = $p_logout || $logout ;
    $reset = gps( 'reset' ) ;
    $p_reset = $p_reset || $reset ;
    $change = gps( 'change' ) ;
    $p_change = $p_change || $change ;

    if( $p_userid && $p_password )
    {
        $username = ($login_with == 'auto') ? safe_count( 'txp_users', "name='$p_userid'" ) : 0 ;

        if( $username == 0 && $login_with != 'username' )
        { // Email probably given, retrieve user name if possible
            $p_userid = safe_rows( 'name', 'txp_users', "email='$p_userid'" ) ;
            $p_userid = (count( $p_userid ) == 1) ? $p_userid[ 0 ][ 'name' ] : '' ;
        }

        $login_redir = ($login_redir==='link') ? $link : $login_redir ;
        $login_failed = ($txp_user = _cbe_fa_auth( $login_redir, 0, $p_userid, $p_password, $p_stay )) === false ;
        _cbe_fa_logged_in( $cbe_fa_user, $txp_user ) ;
    }
    elseif( $p_logout )
    {
        if( $logout && !$logout_redir )
            $logout_redir = preg_replace( "/[?&]logout=1/", "", serverSet('REQUEST_URI') ) ;

        $txp_user = _cbe_fa_auth( $logout_redir, 1 ) ;
        _cbe_fa_logged_in( $cbe_fa_user, false ) ;
    }
    else
        $txp_user = _cbe_fa_logged_in( $cbe_fa_user ) ? $cbe_fa_user[ 'name' ] : false ;

    $out = array() ;
    $invite = '' ;
    $part_0 = EvalElse( $thing, 0 ) ;
    $part_1 = EvalElse( $thing, 1 ) ;
    if( $txp_user === false )
    {
        $out[] = parse( $part_0 ) ;

        if( $show_login )
        {
            if( $p_reset )
            {   // Resetting password in progress
                $invite = _cbe_fa_gTxt( 'password_reset' ) ;
                $out[]  = callback_event( 'cbefrontauth.reset_password', 'cbe_fa_before_login', 0
                                        , ps('step') ? array( 'p_userid'   => $p_userid
                                                            , 'login_with' => $login_with
                                                            , 'tag_error'  => $tag_error
                                                            , 'class_error' => $class_error )
                                                     : null ) ;
            }
            else
            {   // We are not resetting the password at the moment, display login form
                if( isset( $login_failed ) && $login_failed )
                    $out[] = doTag( _cbe_fa_gTxt( 'login_failed' ), $tag_error, $class_error ) ;
                $invite = $login_invite ;
                $out[]  = _cbe_fa_inout( array( 'label'      => $login_label
                                              , 'form'       => $login_form
                                              , 'show_stay'  => $show_stay
                                              , 'show_reset' => $show_reset
                                              ) + compact( 'wraptag', 'class', 'break', 'breakclass' ) ) ;
                if( $show_reset )
                    $out[] = callback_event( 'cbefrontauth.reset_password', 'cbe_fa_after_login' ) ;
            }
        }
    }
    else
    {
        if( (!$name && !$level)
            ||
            _cbe_fa_privileged( $name, $level, $cbe_fa_user[ 'name' ], $cbe_fa_user[ 'privs' ] )
          )
        {
            if( $link )
                $out[] = cbe_frontauth_link( array( 'label' => $linklabel ) + compact( 'link', 'target' ) ) ;

            if( $thing )
                $out[] = parse( $part_1 ) ;
            elseif( $form )
                $out[] = parse_form( $form ) ;
        }
        else
            $out[] = parse( $part_0 ) ;

        if( $show_logout )
        {
            if( $p_change )
            {   // Changing password in progress
                $invite = _cbe_fa_gTxt( 'change_password' ) ;
                $out[]  = callback_event( 'cbefrontauth.change_password', 'cbe_fa_before_logout', 0
                                        , ps('step') ? array( 'p_userid'     => $txp_user
                                                            , 'p_password'   => $p_password
                                                            , 'p_password_1'
                                                               => strip_tags( ps( 'p_password_1' ) )
                                                            , 'p_password_2'
                                                               => strip_tags( ps( 'p_password_2' ) )
                                                            , 'tag_error'    => $tag_error
                                                            , 'class_error'  => $class_error )
                                                     : null ) ;
            }
            else
            {   // We are not changing the password at the moment, display logout form
                $invite = $logout_invite ;
                $out[] = _cbe_fa_inout( array( 'label'       => $logout_label
                                             , 'form'        => $logout_form
                                             , 'type'        => $logout_type
                                             , 'show_change' => $show_change
                                             , 'p_change'    => $p_change
                                             , 'tag_error'   => $tag_error
                                             , 'class_error' => $class_error
                                             ) + compact( 'wraptag', 'class', 'break', 'breakclass' ) ) ;
                if( $show_change )
                    $out[] = callback_event( 'cbefrontauth.change_password', 'cbe_fa_after_logout' ) ;
            }
        }
    }

//    return( doLabel( $invite, $tag_invite ) . doWrap( $out, $wraptag, $break, $class, $breakclass ) ) ;
    return( doLabel( $invite, $tag_invite ) . doWrap( $out, $wraptag, '', $class ) ) ;
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. cbe_frontauth

This client-side plugin lets your users (or you) manage backend connection from frontend, i.e. connect and disconnect as they (you) would do from backend.
You can thus make things visible and open actions for connected users only.

Developed and tested with Textpattern 4.4.1, then 4.5-beta.
*Please read the first "Quick start":#quick-start paragraph to avoid (as much as possible) unexpected behaviors.*
A few examples (in french) can be found in the "demonstration page":http://www.clairebrione.com/demo-cbe_frontauth.

Claire Brione - http://www.clairebrione.com/

h2(#features). Features

* automatically generate a @<a href="#login-logout-box">login/logout box</a>@...
* ... or independent @<a href="#login-area">login</a>@ or @<a href="#logout-area">logout</a>@ forms and links
* choose if th user must connect with his/her @<a href="#login-method">username, or email address, or one of both</a>@
* show/hide content depending on whether a user is connected or not (see @<a href="#protect-parts">protect parts of a page</a>@)
* set @<a href="#automatic-redirect">automatic redirections</a>@ after login and/or logout
* optional: define your own default values for @<a href="#setting-invites">login/logout invites</a>@ and @<a href="#setting-labels">button/link labels</a>@, @<a href="#login-method">define login method</a>@
* override these values anywhere in the page if you need to
* also provides @<a href="#additional-tags">additional tags</a>@ to ease scripter's life
* and @<a href="#callbacks">hooks for callback functions</a>@ primarily in order to reset or change user's password

h2(#dl-install). Download, installation, support

Also available on "GitHub":https://github.com/ClaireBrione/cbe_frontauth, "textpattern resources":http://textpattern.org/plugins/1234/cbe_frontauth or the "plugin page":http://www.clairebrione.com/cbe_frontauth.

Copy/paste in the Admin > Plugins tab to install or uninstall, activate or desactivate.

Visit the "forum thread":http://forum.textpattern.com/viewtopic.php?id=36552 for support.

h2(#tags-list). Tags list

Alphabetically:

* "cbe_frontauth":#advanced-usage
* "cbe_frontauth_backend":#path-backend
* "cbe_frontauth_box":#login-logout-box
* "cbe_frontauth_edit_article":#edit-article
* "cbe_frontauth_if_connected":#protect-parts
* "cbe_frontauth_invite":#setting-invites
* "cbe_frontauth_label":#setting-labels
* "cbe_frontauth_link":#link-generation
* "cbe_frontauth_if_logged":#protect-parts
* "cbe_frontauth_login":#login-area
* "cbe_frontauth_loginwith":#login-method
* "cbe_frontauth_logname":#form-elements
* "cbe_frontauth_logout":#logout-area
* "cbe_frontauth_password":#form-elements
* "cbe_frontauth_protect":#protect-parts
* "cbe_frontauth_redirect":#automatic-redirect
* "cbe_frontauth_reset":#form-elements
* "cbe_frontauth_stay":#form-elements
* "cbe_frontauth_submit":#form-elements
* "cbe_frontauth_whois":#user-info

h2(#notations). Notations

@Tags and examples are presented with this typography@ (fixed width).

Possible values for attributes are separated by a @ | @ (pipe).

*Bold* means default value.

@...@ (ellipsis) is to be replaced by any custom value, usually a string.

Attributes surrounded by @[@ and @]@ (square brackets) are optional.

h2(#quick-start). Quick start

Message strings are customisable by editing them in the function @_cbe_fa_lang()@. When possible, their default values are pulled from the language table. In most cases, you won't have to edit them as they are already localised.

*What you have to know and care about :*

* The login/logout mechanism relies on cookies. A cookie is attached to one, and only one, subdomain.
* @http://domain.tld@ and @http://www.domain.tld@ are *different subdomains*, even if you present the same content through both URLs.

=> You will have to choose which base URL you want to use (with or without www) and stick to it along all the navigation. This is also a good practice to avoid duplicate content.

Here is how to:

# Plugin load order: as it handles redirections, it has to be loaded before any other plugin. Set by default to **4**, adjust according to your needs.
# Admin > Preferences : give (or verify) your site URL and save.
# Edit the @.htaccess@ file located at the root of your site, and append as closer as possible to @RewriteEngine On@ (replace @domain.tld@ with your actual URL)

EITHER, with www

bc. RewriteCond %{HTTP_HOST} !^www\.domain\.tld$ [NC]
RewriteRule ^(.*) http://www.domain.tld/$1 [QSA,R=301,L]

OR, without www

bc. RewriteCond %{HTTP_HOST} ^www\.domain\.tld$ [NC]
RewriteRule ^(.*) http://domain.tld/$1 [QSA,R=301,L]

_It's time now to start using the plugin_: "allow users to login and logout":#login-logout-box, "redirecting them":#automatic-redirect (or not) after login and/or logout, "serve them special content":#protect-parts, "the rest is up to you":#individual-elements.

@wraptag@, @class@, @break@ and @breakclass@ are supported by every tag and both default to **unset**.

h3(#login-logout-box). Login/logout box: <txp:cbe_frontauth_box />

bc.. <cbe_frontauth_box
  [ login_invite="Connect to textpattern | ..."
    logout_invite="none | ..."
    tag_invite="..."
    login_label="..."
    logout_label="..."
    logout_type="button | link"
    tag_error="span" class_error="cbe_fa_error"
    wraptag="..." class="..." break="..." breakclass="..." ] />

p. Displays

* simple login form if not connected
* "connected as {login name}" and a logout button or link if connected

If login fails, a basic message will appear just before the login form. You can customise its wrapping tag and class.

If you don't want "connected as" message, use as a container tag and put a blank or "anything else":#box-ideas in between:

bc. <txp:cbe_frontauth_box> </txp:cbe_frontauth_box>

h3(#protect-parts). Protect parts of a page: <txp:cbe_frontauth_protect />, <txp:cbe_frontauth_if_logged /> and <txp:cbe_frontauth_if_connected />

bc.. <txp:cbe_frontauth_protect
  [ name="none | comma-separated values"
    level="none | comma-separated values"
    link="none | url"
    linklabel="none | anchor"
    target="_self | _blank"
    wraptag="..." class="..." break="..." breakclass="..." ]>
  What to protect
<txp:else />
  What to display if not connected
</txp:cbe_frontauth_protect>

p. Synonyms: @<txp:cbe_frontauth_if_connected />@ @<txp:cbe_frontauth_if_logged />@ if you find one of these forms more convenient

If connected, you can automatically add a link to click to go somewhere. This link will show first (before any other content).
You do this using the attributes @link@, @linklabel@, optionally @target@ ("_self" opens the link in the same window/tab, "_blank" in a new window/tab).

If you want to display the link anywhere else, or display more than one link, or conditionally show a link, prefer "<cbe_frontauth_link />":#link-generation

h3(#login-method). Login method <txp:cbe_frontauth_loginwith />

What to use as login name : username (as textpattern usually does), email, or auto for automatic detection.

*Caution if using email login method</span> : textpattern doesn't check for duplicate email addresses upon user creation. If someone tries to log in using such an address, it will fail.*

bc.. <cbe_frontauth_loginwith
    value="auto | username | email" />

h3(#automatic-redirect). Automatic redirect: <txp:cbe_frontauth_redirect />

User will be automatically redirected after successful login and/or logout.
Use this tag before any other cbe_frontauth_* as it sets redirection(s) for the whole page.

bc.. <txp:cbe_frontauth_redirect
    for="login | logout | login,logout"
    value="after_login_url | after_logout_url | after_login_url,after_logout_url" />

p. In other words and in details:

bc. <txp:cbe_frontauth_redirect for="login" value="after_login_url" />

sets automatic redirection after login

bc. <txp:cbe_frontauth_redirect for="logout" value="after_logout_url" />

sets automatic redirection after logout

bc. <txp:cbe_frontauth_redirect for="login" value="after_login_url" />
<txp:cbe_frontauth_redirect for="logout" value="after_logout_url" />

sets automatic redirection for both

bc. <txp:cbe_frontauth_redirect for="login,logout" value="after_login_url,after_logout_url" />

sets automatic redirection for both too

h3(#setting-invites). Setting invites globally for the whole page: <txp:cbe_frontauth_invite />

Works the same way "as above":#automatic-redirect:

bc. <txp:cbe_frontauth_invite for="..." value="..." />

Combinations: @login, logout (or logged), tag@

Synonym: @logged@ for logout, if you find this form more convenient. <span class="accent">As synonyms they are mutually exclusive</span> and if both used @logout@ will take precedence.

Can be overridden by any tag that has @invite@ as attribute.

Example:

bc. <txp:cbe_frontauth_invite for="login,logout,tag" invite="Please login,You can logout here,h2" />
<txp:cbe_frontauth_box />
  ... Your page here ...
  ... and in the footer, for example ...
<txp:cbe_frontauth_login invite="Say hello !" tag_invite="span" />

h3(#setting-labels). Setting button and link labels globally for the whole page: <txp:cbe_frontauth_label />

Works the same way "as above":#automatic-redirect too:

bc. <txp:cbe_frontauth_label for="..." value="..." />

Combinations: @login, logout@
Can be overridden by any tag that has @label@ as attribute.

h2(#individual-elements). Take control on individual elements

h3(#login-area). Login area: <txp:cbe_frontauth_login />

bc.. <txp:cbe_frontauth_login
  [ invite="Connect to textpattern | ..."
    tag_invite="none | ..."
    ( {label="Login|..." show_stay="0|1" show_reset="0|1"} | form="none|form name" )
    tag_error="span" class_error="cbe_fa_error"
    wraptag="..." class="..." break="..." breakclass="..." ] />

<txp:cbe_frontauth_login
  [ invite="Connect to textpattern | ..."
    tag_invite="none | ..."
    tag_error="span" class_error="cbe_fa_error"
    wraptag="..." class="..." break="..." breakclass="..." ]>
   form elements
</txp:cbe_frontauth_login>

p. If login fails, a basic message will appear just before the login form. You can customise its wrapping tag and class.

p(#form-elements). Where @form elements@ are:

bc. <txp:cbe_frontauth_logname [label="Name|..." wraptag="..." class="..." break="..." breakclass="..."] />
<txp:cbe_frontauth_password [label="Password|..." wraptag="..." class="..." break="..." breakclass="..."] />
<txp:cbe_frontauth_stay [label="Stay connected with this browser|..." wraptag="..." class="..." break="..." breakclass="..."] />
<txp:cbe_frontauth_reset [label="Password forgotten ?|..." wraptag="..." class="..." break="..." breakclass="..."] />
<txp:cbe_frontauth_submit [label="Login|..." wraptag="..." class="..." break="..." breakclass="..."] />

h3(#logout-area). Logout area: <txp:cbe_frontauth_logout />

bc.. <txp:cbe_frontauth_logout
  [ invite="none|..."
    tag_invite="none|..."
    ( {label="Logout|..." type="button|link" show_change="0|1} | form="none|form name")
    wraptag="..." class="..." break="..." breakclass="..." ] />

<txp:cbe_frontauth_logout
  [ invite="none|..."
    tag_invite="none|..."
    wraptag="..." class="..." break="..." breakclass="..." ]>
   form elements
</txp:cbe_frontauth_logout>

p. Where @form elements@ are:

bc. <txp:cbe_frontauth_submit type="logout" [label="Logout|..." wraptag="..." class="..." break="..." breakclass="..."] />
<txp:cbe_frontauth_link link="logout=1" target="_get" [label="..." wraptag="..." class="..." break="..." breakclass="..."] />

h2(#additional-tags). Additional and special tags

h3(#user-info). Connected user information: <txp:cbe_frontauth_whois />

bc. <txp:cbe_frontauth_whois
    [   type="[name][, RealName][, email][, privs][, last_access]"
        format="as set in preferences|since|rfc822|iso8601|w3cdtf|strftime() string value" wraptag="..."
        break="..." class="..." breakclass="..."] />

@format@ applies to @last_access@ if present.

h3(#path-backend). Path to Textpattern backend: <txp:cbe_frontauth_backend />

bc. <txp:cbe_frontauth_backend />

Returns path to textpattern root (in most cases /textpattern/index.php).

h3(#edit-article). Direct button or link to edit current article (write article)

In an individual article form or enclosed in @<txp:if_individual_article> </txp:if_individual_article>@:

bc. <txp:cbe_frontauth_if_connected>
    <txp:cbe_frontauth_edit_article label="edit|..."  type="button|link" wraptag="..." class="..." break="..." breakclass="..." />
</txp:cbe_frontauth_if_connected>

p. <span class="accent">Why use a button rather than a link ?</span> Answer: as it is enclosed in an HTML form, it allows to go to the edit page without showing parameters in the URL.

h3(#link-generation). Link generation: <txp:cbe_frontauth_link />

bc. <txp:cbe_frontauth_link label="..." link="..." [target="_self|_blank|_get" wraptag="..." class="..." break="..." breakclass="..."] />

@class@ applies to the anchor if there is no @wraptag@ supplied.
@_get@ will add @link@ to the current URL, for example:
URL : http://www.example.com/page

bc. <txp:cbe_frontauth_link label="Logout" link="logout=1" target="_get" />

URL Result : http://www.example.com/page?logout=1

h2(#callbacks). Callbacks

They have been introduced to hook cbe_frontauth's companion, "cbe_members":/cbe_members (see details in the table below).

table(list).
|^.
|_. Event |_. Step |_. What it is |
|-.
| @cbefrontauth.reset_password@ | @cbe_fa_before_login@ | Triggered before showing login form, when resetting password is in progress.
If cbe_members is installed, displays here the "reset password" form, or performs the actual reset if the form is successfully filled in. |
| @cbefrontauth.reset_password@ | @cbe_fa_after_login@ | Triggered after showing login form.
If cbe_members is installed, displays a link to the "reset password" form. |
| @cbefrontauth.change_password@ | @cbe_fa_before_logout@ | Triggered before showing logout form, when changing password is in progress.
If cbe_members is installed, displays the "change password" form, or performs the actual change if the form is successfully filled in. |
| @cbefrontauth.change_password@ | @cbe_fa_after_logout@ | Triggered after showing logout form.
If cbe_members is installed, displays a link to the "change password" form. |

h2(#how-to). How-to: ideas and snippets

h3(#box-ideas). For login/logout box

Replace the standard message with something else:

bc. <txp:cbe_frontauth_box>Welcome !</txp:cbe_frontauth_box>

Or even:

bc. <txp:cbe_frontauth_box>
    Greetings <txp:cbe_frontauth_whois type="RealName" /> !
</txp:cbe_frontauth_box>

h3(#invites-ideas). For invites

bc. <txp:cbe_frontauth_invite
    for="logged"
    value='<txp:cbe_frontauth_whois type="RealName" />'
    />

Note: if a user is connected, the login invite doesn't show and the logout invite takes its place. So we could use @for="logout"@ as well.

h3(#greeting-message). A greeting message

bc. Greetings
<txp:cbe_frontauth_if_connected>
    <txp:cbe_frontauth_whois type="RealName" />
<txp:else />
    dear User
</txp:cbe_frontauth_if_connected>!

h2(#advanced-usage). Advanced usage

p(readme). As previous tags should cover majority's needs, you don't have to read this section if you already achieved what you wanted to.

This is the programmer's corner: it describes attributes for the main function that is called by almost every public tag discussed above.

Here are the parameters for the main function:

bc. <txp:cbe_frontauth>
  What to do/display if connected
<txp:else />
  What to do/display if not connected
</txp:cbe_frontauth>

* form ('') or thing = what to display if logged in
* tag_invite ('') = HTML tag enclosing the label, without brackets
* show_login (1) = whether to display or not a login form, appears only if not logged in
* login_invite ('login_to_textpattern') = invite to login
* login_form ('') = form to build your own HTML login form with txp:cbe_frontauth_login, or txp:cbe_frontauth_logname, cbe_frontauth_password, cbe_frontauth_stay, cbe_frontauth_reset, cbe_frontauth_submit. If not used, a default HTML form is displayed
* login_label ('log_in_button') = label for the login form
* login_with (auto) = whether to use username, or email, or auto detection as user logon
* login_redir ('') = go immediately to path after successful login
* show_stay (0) = used in the generic login form, whether to display or not a checkbox to stay logged in
* show_reset (1) = used in the generic login form, whether to display or not a link to reset password
* show_logout (1) = whether to display or not a default button to log out, appears only if logged in
* logout_invite ('') = invite to logout
* logout_form ('') = form to build your own HTML logout form, or your own link
* logout_label (as set in lang pack) = label for the logout button
* logout_type ('button'), other type is 'link'
* logout_redir ('') = go immediately to path after logout
* show_change (1) = used in the generic logout form, whether to display or not a link to change password
* link ('') = a page to go to if connected
* linklabel ('') = text anchor for link
* target (_self) = _self _blank or _get, whether to open the link in the same window (or tab), or in a new one, or to generate an URL with address link as GET parameter. Works only with hyperlink (not login_redir, not logout_redir)

Checking users and privileges :

* name ('') = list of names to check
* level ('') = list of privilege levels to check

Presentational attributes :

* wraptag (''), class ('')

init = Special attribute for internal use only and documented only for people who want to know :)
Whether to set ('1') or get ('0') global settings for redirections (login_redir, logout_redir), invites (login_invite, logout_invite, tag_invite), labels (login_label, logout_label), login type (login_with) or user's informations. Immediately returns and doesn't display anything.
value = setting to set or get, string or array.

h2(#changelog). Changelog

* 27 Apr 22 - v0.9.8 - Register tags, refactor deprecated function for PHP 7.3+ compatibility (jools-r)
* 01 Jan 20 - v0.9.7.1 - Refactored help and include txplib_admin.php' for txp 4.8 compatibility (gas-kirito)
* 20 Nov 15 - v0.9.7 - "Fix this":http://forum.textpattern.com/viewtopic.php?pid=296720#p296720
* 07 Apr 14 - v0.9.6 - Error when passing presentational attributes from cbe_frontauth_edit_article to cbe_frontauth_link
* 04 Apr 14 - v0.9.5 - Missing last access storage
* 27 Mar 13 - v0.9.4
** Missing initialization for cbe_frontauth_whois
** Error message when login fails
** Local language strings
* 22 Mar 12 - v0.9.3 - Doc typo for cbe_frontauth_invite
* ?? ??? 12 - v0.9.2 - ??
* 22 Mar 12 - v0.9.1 - fixed missing attributes (show_login and show_change) for cbe_frontauth_box
* 21 Mar 12 - v 0.9 - Callback hooks: ability to ask for password reset if not connected, for password change if connected
* 10 Jan 12 - v 0.8 - Introduces <txp:cbe_frontauth_loginwith />, "idea comes from another demand in the textpattern forum":http://forum.textpattern.com/viewtopic.php?pid=256632#p256632.
* 05 Jan 12 - v0.7.1 - Documentation addenda
* 06 Aug 11 - v0.7-beta
** Introduces <txp:cbe_frontauth_edit_article /&gt
** CSRF protection ready
** Documentation improvements
* 29 Jul 11 - v0.6-beta
** Optimizations to avoid multiple calls to database when retrieving user's informations
** Added name and privilege controls à la "<txp:rvm_if_privileged />":http://vanmelick.com/txp/
** Minor changes to documentation
* 27 Jul 11 - v0.5-beta- First public beta release
* 26 Jul 11 - v0.4-beta- Restricted beta release
* 24 Jul 11 - v0.3-dev - Restricted development release
* 23 Jul 11 - v0.2-dev - Restricted development release
* 22 Jul 11 - v0.1-dev - Restricted development release

h2. TODO

* break, breakclass -> in progress, full tests needed
* enhance error messages?

# --- END PLUGIN HELP ---
-->
<?php
}
?>