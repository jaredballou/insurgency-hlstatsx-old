<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner
            
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/

	if ( !defined('IN_HLSTATS') )
	{
		die('Do not access this file directly.');
	}

	flush();
	
	$tblTeams = new Table(
		array(
			new TableColumn(
				'name',
				'Team',
				'width=35'
			),
			new TableColumn(
				'teamcount',
				'Joined',
				'width=10&align=right&append=+times'
			),
			new TableColumn(
				'percent',
				'Percentage of Times',
				'width=40&sort=no&type=bargraph'
			),
			new TableColumn(
				'percent',
				'%',
				'width=10&sort=no&align=right&append=' . urlencode('%')
			)
		),
		'name',
		'teamcount',
		'name',
		true,
		9999,
		'teams_page',
		'teams_sort',
		'teams_sortorder',
		'tabteams',
		'desc',
		true
	);
	
    
	$db->query("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_ChangeTeam
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId=hlstats_Events_ChangeTeam.playerId
		WHERE
			clan=$clan
	");
	list($numteamjoins) = $db->fetch_row();

	$result  = $db->query("SELECT `code`,`name` FROM hlstats_Roles WHERE game='$game'");
	while ($rowdata = $db->fetch_row($result)) 
    { 
        $code = preg_replace("/[ \r\n\t]+/", '', $rowdata[0]);
		$fname[strToLower($code)] = htmlspecialchars($rowdata[1]);
    }
	
	$result = $db->query("
		SELECT
			IFNULL(hlstats_Teams.name, hlstats_Events_ChangeTeam.team) AS name,
			COUNT(hlstats_Events_ChangeTeam.id) AS teamcount,
			ROUND(COUNT(hlstats_Events_ChangeTeam.id) / IF($numteamjoins = 0, 1, $numteamjoins) * 100, 2) AS percent
		FROM
			hlstats_Events_ChangeTeam
		LEFT JOIN hlstats_Teams ON
			hlstats_Events_ChangeTeam.team=hlstats_Teams.code
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId=hlstats_Events_ChangeTeam.playerId
		WHERE
			clan=$clan
			AND hlstats_Teams.game='$game' 
			AND (hidden <>'1' OR hidden IS NULL)
		GROUP BY
			hlstats_Events_ChangeTeam.team
		ORDER BY
			$tblTeams->sort $tblTeams->sortorder,
			$tblTeams->sort2 $tblTeams->sortorder
	");
	
	$numitems = $db->num_rows($result);
	
	if ($numitems > 0)
	{
		printSectionTitle('Team Selection *');
		$tblTeams->draw($result, $numitems, 95);
?>
	<br /><br />
<?php
	}
	
	flush();
	
        $tblRoles = new Table(
        array
		(
            new TableColumn
			(
                'code',
                'Role',
                'width=25&type=roleimg&align=left&link=' . urlencode("mode=rolesinfo&amp;role=%k&amp;game=$game"),
				$fname
            ),
            new TableColumn
			(
                'rolecount',
				'Joined',
				'width=10&align=right&append=+times'
			),
			new TableColumn
			(
				'percent',
				'%',
				'width=10&sort=no&align=right&append=' . urlencode('%')
            ),
			new TableColumn
			(
                'percent',
                'Ratio',
                'width=20&sort=no&type=bargraph'            
			),
            new TableColumn
			(
                'killsTotal',
                'Kills',
                'width=10&align=right'
            ),
            new TableColumn
			(
                'deathsTotal',
                'Deaths',
                'width=10&align=right'
            ),
            new TableColumn
			(
                'kpd',
                'K:D',
                'width=10&align=right'
			)
		),
		'code',
		'rolecount',
		'name',
		true,
		9999,
		'roles_page',
		'roles_sort',
		'roles_sortorder',
		'roles',
		'desc',
		true
	);
	$db->query("DROP TABLE IF EXISTS hlstats_Frags_as");

	$db->query("
	CREATE TEMPORARY TABLE hlstats_Frags_as
	(
		playerId INT(10),
		kills INT(10),
		deaths INT(10),
		role varchar(128) NOT NULL default ''
	)
	");
	
	
	$db->query("
		INSERT INTO
		hlstats_Frags_as
		(
			playerId,
			kills,
			role
		)
		SELECT  
			victimId,
			killerId,
			killerRole
		FROM
			hlstats_Events_Frags
		LEFT JOIN hlstats_Servers ON
			hlstats_Servers.serverId=hlstats_Events_Frags.serverId LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId = hlstats_Events_Frags.killerId
		WHERE   
			hlstats_Servers.game='$game' AND clan = $clan
		");

	$db->query("
		INSERT INTO
			hlstats_Frags_as
		(
			playerId,
			deaths,
			role
		)
		SELECT  
			killerId,
			victimId,
			victimRole
		FROM
			hlstats_Events_Frags
		LEFT JOIN
			hlstats_Servers
		ON
			hlstats_Servers.serverId = hlstats_Events_Frags.serverId
		LEFT JOIN
			hlstats_Players
		ON
			hlstats_Players.playerId = hlstats_Events_Frags.victimId
		WHERE   
			hlstats_Servers.game='$game' AND clan = $clan 
		");
		
	$db->query("DROP TABLE IF EXISTS hlstats_Frags_as_res");
	$db->query("
	CREATE TEMPORARY TABLE hlstats_Frags_as_res
	(
		killsTotal INT(10),
		deathsTotal INT(10),
		role varchar(128) NOT NULL default ''
	)
	");
	
	$db->query("
	INSERT INTO
	hlstats_Frags_as_res
	(
		killsTotal,
		deathsTotal,
		role
	)
	SELECT
	COUNT(hlstats_Frags_as.kills) AS kills, 
	COUNT(hlstats_Frags_as.deaths) AS deaths,
	role 
	from hlstats_Frags_as GROUP by role
	");
	
	$db->query("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_ChangeRole
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId=hlstats_Events_ChangeRole.playerId
		WHERE
			clan=$clan
	");
	list($numrolejoins) = $db->fetch_row();

	$result = $db->query("
		SELECT
			IFNULL(hlstats_Roles.name, hlstats_Events_ChangeRole.role) AS name,
			IFNULL(hlstats_Roles.code, hlstats_Events_ChangeRole.role) AS code,
			COUNT(hlstats_Events_ChangeRole.id) AS rolecount,
			ROUND(COUNT(hlstats_Events_ChangeRole.id) / IF($numrolejoins = 0, 1, $numrolejoins) * 100, 2) AS percent,
			killsTotal,
			deathsTotal,
			ROUND(killsTotal/if(deathsTotal=0,1,deathsTotal), 2) AS kpd
		FROM
			hlstats_Events_ChangeRole
		LEFT JOIN
			hlstats_Roles
		ON
			hlstats_Events_ChangeRole.role = hlstats_Roles.code
		LEFT JOIN
			hlstats_Servers
		ON
			hlstats_Servers.serverId = hlstats_Events_ChangeRole.serverId
		LEFT JOIN
			hlstats_Frags_as_res
		ON
			hlstats_Frags_as_res.role = hlstats_Events_ChangeRole.role
		LEFT JOIN
			hlstats_Players
		ON
			hlstats_Players.playerId = hlstats_Events_ChangeRole.playerId
		WHERE
			hlstats_Servers.game='$game'
			AND hlstats_Players.clan=$clan
			AND (hidden <>'1' OR hidden IS NULL)
			AND hlstats_Roles.game = '$game'
		GROUP BY
			hlstats_Events_ChangeRole.role
		ORDER BY
			$tblRoles->sort $tblRoles->sortorder,
			$tblRoles->sort2 $tblRoles->sortorder
	");
	
	$numitems = $db->num_rows($result);
	
	if ($numitems > 0)
	{
		printSectionTitle('Role Selection *');
		$tblRoles->draw($result, $numitems, 95);
?>
	<br /><br />
<?php
	}
?>