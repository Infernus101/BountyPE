<?php

/*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*/

namespace Infernus101;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\{Command, CommandSender};
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener{
    public $db;
	public function onEnable(){
    $this->getLogger()->info("§b§lLoaded Bounty by Infernus101");
		$files = array("config.yml");
		foreach($files as $file){
			if(!file_exists($this->getDataFolder() . $file)) {
				@mkdir($this->getDataFolder());
				file_put_contents($this->getDataFolder() . $file, $this->getResource($file));
			}
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		$this->db = new \SQLite3($this->getDataFolder() . "bounty.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS bounty (player TEXT PRIMARY KEY COLLATE NOCASE, money INT);");
	}
    	public function bountyExists($playe) {
		$result = $this->db->query("SELECT * FROM bounty WHERE player='$playe';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	    }
		public function getBountyMoney($play){
        $result = $this->db->query("SELECT * FROM bounty WHERE player = '$play';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["money"];
        }
	    public function deleteBounty($pla){
		$this->db->query("DELETE FROM bounty WHERE player = '$pla';");
	    }
		public function addBounty($player, $mon){
		if($this->bountyExists($player)){
		   $stmt = $this->db->prepare("INSERT OR REPLACE INTO bounty (player, money) VALUES (:player, :money);");  	
           $stmt->bindValue(":player", $player);
		   $stmt->bindValue(":money", $this->getBountyMoney($player) + $mon);
		   $result = $stmt->execute();	   
		 }
		 if(!$this->bountyExists($player)){
		   $stmt = $this->db->prepare("INSERT OR REPLACE INTO bounty (player, money) VALUES (:player, :money);");  	
           $stmt->bindValue(":player", $player);
		   $stmt->bindValue(":money", $mon);
		   $result = $stmt->execute();	   
	     }
		}
		public function onDeath(PlayerDeathEvent $event) {
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent) {
            $player = $event->getEntity();
			$name = $player->getName();
			$lowr = strtolower($name);
            $killer = $event->getEntity()->getLastDamageCause()->getDamager();
			$name2 = $killer->getName();
			if($player instanceof Player){
				if($this->bountyExists($lowr)){
					$money = $this->getBountyMoney($lowr);
					$killer->sendMessage("§b[BOUNTY]§a>§b You get extra §6$money §bfrom bounty for killing §a$name"."§b!");
					EconomyAPI::getInstance()->addMoney($killer->getName(), $money);
				if($this->cfg->get("bounty_fine") == 1){
					$perc = $this->cfg->get("fine_percentage");
					$fine = ($money*$perc)/100;
					if(EconomyAPI::getInstance()->myMoney($player->getName()) > $fine){
					  	EconomyAPI::getInstance()->reduceMoney($player->getName(), $fine);
						$player->sendMessage("§b[BOUNTY]§a>§c Your §6$fine"."$ §cwas taken as Bounty fine! Bounty Fine = $perc Percent of Bounty on you!");
					}
					if(EconomyAPI::getInstance()->myMoney($player->getName()) <= $fine){
					  	EconomyAPI::getInstance()->setMoney($player->getName(), 0);
						$player->sendMessage("§b[BOUNTY]§a>§c Your §6$fine"."$ §cwas taken as Bounty fine! Bounty Fine = $perc Percent of Bounty on you!");
					}
				}
					$this->deleteBounty($lowr);
				}
		 }
    }
}
	    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		////////////////////// BOUNTY //////////////////////
		 if(strtolower($cmd->getName()) == "bounty"){	
		   if(!isset($args[0])){
		        $sender->sendMessage("§cUsage: /bounty <set | me | search | top | about>");
			    return false;
		   }	
		 switch(strtolower($args[0])){
		 case "set":
		   if(!(isset($args[1])) or !(isset($args[2]))){
			   $sender->sendMessage("§cUsage: /bounty set <player> <money>");
			   break;
		   }
		   $invited = $args[1];
		   $lower = strtolower($invited);
		   $name = strtolower($sender->getName());
		   if($lower == $name){
			   $sender->sendMessage("§bBOUNTY> §cYou cannot place bounties on yourself!");
			   break;
		   }
		    $playerid = $this->getServer()->getPlayerExact($lower);
			$money = $args[2];
		   if(!$playerid instanceof Player) {
			   $sender->sendMessage("§bBOUNTY> §cPlayer not found!");
			   break;
		   }
		   if(!is_numeric($args[2])) {
				$sender->sendMessage("§cUsage: /bounty set $args[1] <money>\n§bBOUNTY> §cMoney has to be a number!");
				break;
		   }
		   $min = $this->cfg->get("min_bounty");
		   if($money < $min){
			  $sender->sendMessage("§bBOUNTY> §cMoney has to be greater than $min"."$");
			  break;
		   }
		   if($fail = EconomyAPI::getInstance()->reduceMoney($sender, $money)) {
		   $this->addBounty($lower, $money);
		   $sender->sendMessage("§bBOUNTY> §aSuccessfully added §6$money"."$ §abounty on §e$invited");
		   $playerid->sendMessage("§bBOUNTY> §cA Bounty has been added on you for §6$money"."$ §cby §a$name\n§6Check total bounty on you by /bounty me");
		   break;
		   }else {
						switch($fail){
							case EconomyAPI::RET_INVALID:
								$sender->sendMessage("§bBOUNTY> §cYou do not have enough money to set that bounty!");
								break;
							case EconomyAPI::RET_CANCELLED:
								$sender->sendMessage("§bBOUNTY> §6ERROR!");
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage("§bBOUNTY> §6ERROR!");
								break;
						}
					}
		   break;
		   case "me":
			   $lower = strtolower($sender->getName());
			   if(isset($args[1])){
				   $sender->sendMessage("§cUsage: /bounty me");
				   break;
			   }
			   if(!$this->bountyExists($lower)){
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aNo current bounties detected on you!\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   break;
			   }
			   if($this->bountyExists($lower)){
				   $bounty = $this->getBountyMoney($lower);
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aBounty on you: §6$bounty"."$\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   break;
			   }
			   break;
		   
		   case "search":
			   if(!isset($args[1])){
				   $sender->sendMessage("§cUsage: /bounty search <player>");
				   break;
			   }
			   $lower = strtolower($args[1]);
			   if(!$this->bountyExists($lower)){
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aNo curent bounties on $args[1]".".\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   break;
			   }
			   if($this->bountyExists($lower)){
				   $bounty = $this->getBountyMoney($lower);
				   $sender->sendMessage("§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=\n§aBounty on $args[1]: §6$bounty"."$\n§d=+=+=+=+=+=+= §bBounty §d=+=+=+=+=+=+=");
				   break;
			   }
			       break;
		   case "top":
		       if(isset($args[1])){
				   $sender->sendMessage("§cUsage: /bounty top");
				   break;
			   }
			          $sender->sendMessage("§a--------- §eMOST WANTED LIST §a---------");
		              $result = $this->db->query("SELECT * FROM bounty ORDER BY money DESC LIMIT 10;"); 			
				      $i = 1; 
					  while($row = $result->fetchArray(SQLITE3_ASSOC)){
						    $play = $row["player"];
							$money = $row["money"];
							$sender->sendMessage("§f§l$i. §r§a$play §f--> §6$money"."$");
						    $i++; 
				      }
		    break;
		   case "about":
		    $sender->sendMessage("§bBounty v1.5 by §aInfernus101\n§eCheckout my MCPE server IP: FallenTech.tk Port: 19132");
		    break;   
		   default:
		    $sender->sendMessage("§cUsage: /bounty <set | me | search | top | about>");
		    break;
			 }
	}
  }
}

