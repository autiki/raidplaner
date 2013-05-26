<?php

function msgQueryProfile( $aRequest )
{
    global $gRoles;

    if ( validUser() )
    {
        $UserId = UserProxy::getInstance()->UserId;

        if ( validAdmin() && isset( $aRequest["id"] ) )
        {
            $UserId = intval( $aRequest["id"] );
        }

        $Connector = Connector::getInstance();

        // Admintool relevant data

        $Users = $Connector->prepare( "SELECT Login, Created, ExternalBinding, BindingActive FROM `".RP_TABLE_PREFIX."User` WHERE UserId = :UserId LIMIT 1" );
        $Users->bindValue( ":UserId", $UserId, PDO::PARAM_INT );

        if ( !$Users->execute() )
        {
            postErrorMessage( $User );
        }
        else
        {
            $Data = $Users->fetch( PDO::FETCH_ASSOC );

            echo "<userid>".$UserId."</userid>";
            echo "<name>".$Data["Login"]."</name>";
            echo "<bindingActive>".(($Data["BindingActive"] == "true") ? "true" : "false")."</bindingActive>";
            echo "<binding>".$Data["ExternalBinding"]."</binding>";
            
            $Created = $Data["Created"];
        }

        $Users->closeCursor();

        // Load characters
        
        if ( $UserId == UserProxy::getInstance()->UserId )
        {
            foreach ( UserProxy::getInstance()->Characters as $Data )
            {
                echo "<character>";
                echo "<id>".$Data->CharacterId."</id>";
                echo "<name>".$Data->Name."</name>";
                echo "<class>".$Data->ClassName."</class>";
                echo "<mainchar>".(($Data->IsMainChar) ? "true" : "false")."</mainchar>";
                echo "<role1>".$Data->Role1."</role1>";
                echo "<role2>".$Data->Role2."</role2>";
                echo "</character>";
            }
        }
        else
        {
            $CharacterSt = $Connector->prepare( "SELECT * FROM `".RP_TABLE_PREFIX."Character` ".
                                                "WHERE UserId = :UserId ".
                                                "ORDER BY Mainchar, Name" );

            $CharacterSt->bindValue(":UserId", $UserId, PDO::PARAM_INT);
            $CharacterSt->execute();
            
            while ( $Row = $CharacterSt->fetch( PDO::FETCH_ASSOC ) )
            {
                echo "<character>";
                echo "<id>".$Row["CharacterId"]."</id>";
                echo "<name>".$Row["Name"]."</name>";
                echo "<class>".$Row["Class"]."</class>";
                echo "<mainchar>".(($Row["Mainchar"]) ? "true" : "false")."</mainchar>";
                echo "<role1>".$Row["Role1"]."</role1>";
                echo "<role2>".$Row["Role2"]."</role2>";
                echo "</character>";
            }
            
            $CharacterSt->closeCursor();
        }
        
        // Total raid count

        $NumRaids = 0;
        $Raids = $Connector->prepare( "SELECT COUNT(*) AS `NumberOfRaids` FROM `".RP_TABLE_PREFIX."Raid` WHERE Start > :Registered AND Start < FROM_UNIXTIME(:Now)" );
        $Raids->bindValue( ":Now", time(), PDO::PARAM_INT );
        $Raids->bindValue( ":Registered", $Created, PDO::PARAM_STR );

        if ( !$Raids->execute() )
        {
            postErrorMessage( $User );
        }
        else
        {
            $Data = $Raids->fetch( PDO::FETCH_ASSOC );
            $NumRaids = $Data["NumberOfRaids"];
        }

        $Raids->closeCursor();

        // Load attendance

        $Attendance = $Connector->prepare(  "Select `Status`, `Role`, COUNT(*) AS `Count` ".
                                            "FROM `".RP_TABLE_PREFIX."Attendance` ".
                                            "LEFT JOIN `".RP_TABLE_PREFIX."Raid` USING(RaidId) ".
                                            "WHERE UserId = :UserId AND Start > :Registered AND Start < FROM_UNIXTIME(:Now) ".
                                            "GROUP BY `Status`, `Role` ORDER BY Status" );

        $Attendance->bindValue( ":UserId", $UserId, PDO::PARAM_INT );
        $Attendance->bindValue( ":Registered", $Created, PDO::PARAM_STR );
        $Attendance->bindValue( ":Now", time(), PDO::PARAM_INT );

        if ( !$Attendance->execute() )
        {
            postErrorMessage( $Attendance );
        }
        else
        {
            $AttendanceData = array(
                "available"   => 0,
                "unavailable" => 0,
                "ok"          => 0 );

            // Initialize roles

            $RoleKeys = array_keys($gRoles);

            foreach ( $RoleKeys as $RoleKey )
            {
                $AttendanceData[$RoleKey] = 0;
            }

            // Pull data

            while ( $Data = $Attendance->fetch( PDO::FETCH_ASSOC ) )
            {
                if ( $Data["Status"] != "undecided" )
                    $AttendanceData[ $Data["Status"] ] += $Data["Count"];

                if ( $Data["Status"] == "ok" )
                {
                    $RoleIdx = intval($Data["Role"]);
                    if ( $RoleIdx < sizeof($RoleKeys) )
                    {
                        $ResolvedRole = $RoleKeys[ $RoleIdx ];
                        $AttendanceData[ $ResolvedRole ] += $Data["Count"];
                    }
                }
            }

            echo "<attendance>";
            echo "<raids>".$NumRaids."</raids>";

            while( list($Name, $Count) = each($AttendanceData) )
            {
                echo "<".$Name.">".$Count."</".$Name.">";
            }

            echo "</attendance>";
        }

        $Attendance->closeCursor();
    }
    else
    {
        echo "<error>".L("AccessDenied")."</error>";
    }
}

?>