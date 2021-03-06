<?php

namespace App\Services\SeasonGeneratorService;



use App\Models\Group;
use App\Models\Season;
use App\Models\Team;

use App\Repositories\Contracts\ITeam;
use App\Repositories\Contracts\ISeason;
use App\Repositories\Contracts\IAbsence;


class AbstractDoubleGenerator
{
    /** @var App\Repositories\Contracts\ISeason */
    protected $season;
    /** @var App\Repositories\Contracts\IAbsence */
    protected $absence;
    /** @var App\Repositories\Contracts\ITeam */
    protected $team;

    public function __construct(ISeason $seasonRepo, IAbsence $absenceRepo, ITeam $teamRepo) 
    {
        $this->season = $seasonRepo;
        $this->absence = $absenceRepo;
        $this->team = $teamRepo;
    }

     /**
     * Generates the season
     *
     * @param  int  $seasonId
     * @return json
     */
    public function generateSeason(Season $season)
    {
        //create all possible teams
        $allPossibleTeamArray = $this->createAllPossibleTeams($season->group);

        $gamesArray = array();

        //create the personArray to store data for the season
        $gamesArray['person'] = $this->createPersonStats($season);

        //get all date for the season
        $seasonDates = $this->getPlayDates($season->begin, $season->end);

        foreach ($seasonDates as $seasonDate) {
            $key = $seasonDate['date'];
            //Gameday
            $gamesArray['season'][$key]['datum'] = $seasonDate['date'];

            //this team array will be used to remove played teams and absence teams
            $gamesArray['allTeams'] = $allPossibleTeamArray;
            $gamesArray['person'] = $this->addOneNonPlayDay($gamesArray['person']);

            $gamesArray = $this->createDayTeams($seasonDate, $gamesArray);
        }
        return $this->createJsonSeason($gamesArray['season'], $season);
    }

    /**
     * returns the season Calendar
     *
     * @param  object Season
     * @return json
     */
    public function getSeasonCalendar(Season $season)
    {
        $gamesArray = array();

        foreach ($season->teams as $key=>$team) {
            $date = $team->date;
            $teamnumber = $team->team;
            $playerId = $team->group_user_id;
            
            //Gameday
            $gamesArray[$date]['datum'] = $date;
            $gamesArray[$date][$teamnumber][$playerId]['teamId'] = $team->id;;
            if (isset($gamesArray[$date][$teamnumber]['player1']) === false) {
                $gamesArray[$date][$teamnumber]['player1'] = $playerId;
            } else {
                $gamesArray[$date][$teamnumber]['player2'] = $playerId;
            }      
            $gamesArray[$date]['teamIds'][$team->id]['teamId'] = $team->id;
            $gamesArray[$date]['teamIds'][$team->id]['team'] = $teamnumber;
            $gamesArray[$date]['teamIds'][$team->id]['groupUserId'] = $playerId;
            $gamesArray[$date]['teamIds'][$team->id]['replacement'] = $team->ask_for_replacement;
        }
        return $this->createJsonSeason($gamesArray, $season);
    }

    /**
     * Saves a the team to the database
     * @param $jsonSeason
     */
    protected function saveTeam($seasonId, $day, $teamNumber, $groupUserId = null)
    {
        $team = new Team();
        $team->season_id = $seasonId;
        $team->date = $day;
        $team->team = $teamNumber;
        $team->group_user_id = $groupUserId;
        $this->team->saveTeam($team);
    }

    /**
     * Returns the weekly play dates
     *
     * @param  date  $beginDate
     * @param  date  $endDate
     * @return Array
     */
    public function getPlayDates($beginDate, $endDate)
    {
        $startDate = new \DateTime($beginDate);
        $endDate = new \DateTime($endDate);
        $arrayDates = array();

        while ($startDate <= $endDate) {
            $arrayDates[]['date'] = $startDate->format("Y-m-d");
            $startDate->add(new \DateInterval('P7D'));
        }
        return $arrayDates;
    }

    /**
     * create all teams that can be created from the group of people that is given
     * @param $group
     * @return array
     */
    protected function createAllPossibleTeams(Group $group)
    {
        $teamArray = array();
        $z = 0;
        foreach ($group->groupUsers as $user1) {
            foreach ($group->groupUsers as $user2) {
                if ($user1->id < $user2->id) {
                    $teamArray[$z]['player1'] = $user1->id;
                    $teamArray[$z]['player2'] = $user2->id;
                    $z++;
                }
            }
        }
        return $teamArray;
    }

     /**
     * add a non played gameday to the user, this will be used to make sure a user can play on a regular base
     * @param $personArray
     * @return mixed
     */
    protected function addOneNonPlayDay($personArray)
    {
        foreach ($personArray as $key => $Person) {
            $personArray[$key]['nonPlayedWeeks']++;
        }
        return $personArray;
    }

    /**
     * Removes all teams where a user is absence
     * @param $date
     * @param $personArray
     * @param $teamArray
     * @return array
     */
    protected function removeAbsenceTeams($date, $personArray, $teamArray)
    {
        foreach ($personArray as $person) {
            if (isset($person['datumAbsent'][$date]) === false) {
                continue;
            }
            $teamArray = $this->RemoveTeam($person['id'], $teamArray);
        }
        return array_values($teamArray);
    }

    /**
     * Return all teams that haven't played
     *
     * @param  Array  $drawTeamArray
     * @param  Array  $gamesArray
     * @param  string $teamnumber
     * @return Array
     */
    protected function removeAllPlayedGames($drawTeamArray, $gamesArray, $teamnumber)
    {
        //to do after creating the first games
        foreach ($gamesArray as $key=>$games) {
            if (isset($games[$teamnumber]['player1']) === false OR isset($games[$teamnumber]['player2']) === false) {
                continue;
            }

            foreach ($drawTeamArray AS $key2=>$drawTeam) {
                $player1 = isset($drawTeam['player1']) === true ? $drawTeam['player1'] : "";
                $player2 = isset($drawTeam['player2']) === true ? $drawTeam['player2'] : "";
                if ($games[$teamnumber]['player1'] == $player1 AND $games[$teamnumber]['player2'] == $player2) {
                    unset($drawTeamArray[$key2]);
                }
            }
        }
        return array_values($drawTeamArray);
    }

     /**
     * create a team meeting the requirements of the class
     * @param $teamArray
     * @param $personArray
     * @param $teamNumber
     * @return int
     */
    protected function createRandomTeam($teamArray, $personArray, $teamNumber)
    {
        $lowestGames = $lowestTeamPlays = 100;
        $highestGames = $highestTeamPlays = 0;

        //check the lowest and highest games and teammatches
        foreach ($personArray as $person) {
            $person['totalGames'] < $lowestGames ? $lowestGames = $person['totalGames'] : "";
            $person['totalGames'] > $highestGames ? $highestGames = $person['totalGames'] : "";

            $person[$teamNumber] < $lowestTeamPlays ? $lowestTeamPlays = $person[$teamNumber] : "";
            $person[$teamNumber] > $highestTeamPlays ? $highestTeamPlays = $person[$teamNumber] : "";
        }
        //if lowest and highest are the same add one to the total
        ($lowestGames+1) > $highestGames ? $highestGames++ : "";
        ($lowestTeamPlays+1) > $highestTeamPlays ? $highestTeamPlays++ : "";

        //this loop will determen if de team meets all the required conditions
        for ($x=0;$x<200;$x++) {
            //this loop will determen if the player hasn't had a long pause
            for ($z=0;$z<50;$z++) {
                //this loop will determen if the persons hasn't been in the same team;
                for ($y=0;$y<50;$y++) {
                    //determen a random team
                    $random = rand(0, count($teamArray)-1);
                    $teamPlayer1 = isset($teamArray[$random]['player1']) === true ? $teamArray[$random]['player1'] : 9999999;
                    $teamPlayer2 = isset($teamArray[$random]['player2']) === true ? $teamArray[$random]['player2'] : 9999999;

                    if($teamPlayer1 === 9999999 OR $teamPlayer2 === 9999999){
                        continue;
                    }

                    //if players haven't played for more then 1 week then break the loop
                    if($personArray[$teamPlayer1]['nonPlayedWeeks'] > 1 OR $personArray[$teamPlayer2]['nonPlayedWeeks'] > 1){
                        break;
                    }
                }
                //if player1 or player2 hasn't played for more then 2 weeks the loop will break and go on to the next controle
                if (isset($personArray[$teamPlayer1]['against'][$teamPlayer2]['name']) === false AND isset($personArray[$teamPlayer2]['against'][$teamPlayer1]['name']) === false) {
                    break;
                }
            }
            //check if the meet the required highest games and highest team plays
            if($teamPlayer1 === 9999999 OR $teamPlayer2 === 9999999){
                continue;
            }

            if ($personArray[$teamPlayer1]['totalGames'] < $highestGames AND $personArray[$teamPlayer2]['totalGames'] < $highestGames AND $personArray[$teamPlayer1][$teamNumber] < $highestTeamPlays AND $personArray[$teamPlayer2][$teamNumber] < $highestTeamPlays) {
                break;
            }
        }
        return $random;
    }

    /**
     * Removes all possible teams of a given user
     * @param $userId
     * @param $teamArray
     * @return array
     */
    protected function removeTeam($userId, $teamArray)
    {
        foreach ($teamArray as $key=>$team) {
            if ($userId == $team['player1'] OR $userId == $team['player2']) {
                unset($teamArray[$key]);
            }
        }
        return array_values($teamArray);
    }

     /**
     * Return all absences of a user in a season
     *
     * @param  int  $userId
     * @param  int  $seasonId
     * @return Array
     */
    protected function getUserAbsenceDays($groupUserId, $seasonId)
    {
        $absences = $this->absence->getUserAbsence($seasonId, $groupUserId);
        $absenceArray = array();
        foreach ($absences as $absence) {
            $absenceArray[$absence->date] = $absence->date;
        }
        return $absenceArray;
    }

    /**
     * this function will create a json of the season. This will be sent to the screen so when the season is ok it can be saved in an easy way.
     * The json should always have the same structure so it can be saved in the same way if there are more generators
     * @param $gamesArray
     * @param $seasonId
     * @return string
     */
    protected function createJsonSeason($gamesArray, Season $season)
    {
        $getNow = new \Carbon\Carbon();
        $getNow->addDay(-1);
        $nextDate = new \Carbon\Carbon();
        $nextDate->addDay(14);

        $arrayJson = array();
        $arrayJson['seasonData']  = $season;
        $arrayJson['absenceData'] = $this->absence->getSeasonAbsenceArray($season->id);
        $arrayJson['groupUserData'] = $this->team->getSeasonUsers($season->id);
        $arrayJson['generateGroupUserData'] =  $season->group->groupUsers;

        $x=0;
        $y=0;
        foreach ($gamesArray as $game) {
            $datum =  $game['datum'];

            $prepareGroupUser = $arrayJson['generateGroupUserData'] ;
            if(count($arrayJson['groupUserData']) > 0){
                $prepareGroupUser = $arrayJson['groupUserData'];
            }
            
            foreach($prepareGroupUser AS $groupUser){
                $arrayJson['data'][$y]['user'][$groupUser['id']]['groupUser'] = $groupUser['id'];
                $arrayJson['data'][$y]['user'][$groupUser['id']]['team'] = "";
                $arrayJson['data'][$y]['user'][$groupUser['id']]['teamId'] ="";
                $arrayJson['data'][$y]['user'][$groupUser['id']]['replacement'] = 0;
            }
            
            if ($getNow <= \Carbon\Carbon::parse($datum) && $nextDate >= \Carbon\Carbon::parse($datum)) {
                $arrayJson['currentPlayDay'] = $y;
                $nextDate->addDay(-14);
            }

            for ($z=1;$z<=4;$z++) {
                $team = 'team'.$z;
                $teamplayerOne = isset($game[$team]['player1']) === true ? $game[$team]['player1'] : "";
                $teamplayerTwo = isset($game[$team]['player2']) === true ? $game[$team]['player2'] : "";

                isset($arrayJson['stats'][$teamplayerOne][$team]) === true ? $arrayJson['stats'][$teamplayerOne][$team]++ : $arrayJson['stats'][$teamplayerOne][$team] = 1;
                isset($arrayJson['stats'][$teamplayerTwo][$team]) === true ? $arrayJson['stats'][$teamplayerTwo][$team]++ : $arrayJson['stats'][$teamplayerTwo][$team] = 1;
                isset($arrayJson['stats'][$teamplayerOne]['total']) === true ? $arrayJson['stats'][$teamplayerOne]['total']++ : $arrayJson['stats'][$teamplayerOne]['total'] = 1;
                isset($arrayJson['stats'][$teamplayerTwo]['total']) === true ? $arrayJson['stats'][$teamplayerTwo]['total']++ : $arrayJson['stats'][$teamplayerTwo]['total'] = 1;
                
                $x++;
                $teamId = 0;
                $arrayJson['data'][$y]['day'] = $datum;
                if($teamplayerOne > 0){
                    $teamId = isset($game[$team][$teamplayerOne]['teamId']) === true ? $game[$team][$teamplayerOne]['teamId'] : "";

                    $arrayJson['data'][$y]['user'][$teamplayerOne]['groupUser'] = $teamplayerOne;
                    $arrayJson['data'][$y]['user'][$teamplayerOne]['team'] = $team;
                    $arrayJson['data'][$y]['user'][$teamplayerOne]['teamId'] = $teamId;
                    if(isset($game['teamIds'][$teamId]['replacement']) === true){
                        $arrayJson['data'][$y]['user'][$teamplayerOne]['replacement']  = $game['teamIds'][$teamId]['replacement'];
                    }
                }

                if($teamplayerTwo > 0){
                    $teamId = isset($game[$team][$teamplayerTwo]['teamId']) === true ? $game[$team][$teamplayerTwo]['teamId'] : "";

                    $arrayJson['data'][$y]['user'][$teamplayerTwo]['groupUser'] = $teamplayerTwo;
                    $arrayJson['data'][$y]['user'][$teamplayerTwo]['team'] = $team;
                    $arrayJson['data'][$y]['user'][$teamplayerTwo]['teamId'] = $teamId;
                    if(isset($game['teamIds'][$teamId]['replacement']) === true){
                        $arrayJson['data'][$y]['user'][$teamplayerTwo]['replacement']  = $game['teamIds'][$teamId]['replacement'];
                    }
                }
                /** end new array to build the calendar */

            }
            /** new array to build the calendar */
            $arrayJson['data'][$y]['day'] = $datum;
            if(isset($game['teamIds']) === true){
                foreach($game['teamIds'] AS $teamIds){
                    $teamId = $teamIds['teamId'];
                    $arrayJson['data'][$y]['teams'][$teamId]['teamId'] = $teamId;
                    $arrayJson['data'][$y]['teams'][$teamId]['team'] = $teamIds['team'];
                    $arrayJson['data'][$y]['teams'][$teamId]['groupUserId'] = $teamIds['groupUserId'];
                }
            }            
             /** end new array to build the calendar */
            $y++;
        }
        return $arrayJson;
    }
    

    
}