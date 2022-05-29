<?php

namespace tss\TSProtectChest;

use alvin0319\Area\area\Area;
use alvin0319\Area\area\AreaManager;
use alvin0319\Area\AreaLoader;
use pocketmine\block\BlockLegacyIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use function array_push;
use function mkdir;
use function str_replace;
use function strtolower;

class TSProtectChest extends PluginBase implements Listener{

	/** @var Config[] */
	public array $config = [];

	public array $db = [];

	/** @var AreaManager */
	public AreaManager $areaProvider;

	protected function onEnable() : void{
		$this->getLogger()->info("§b인식이 되었습니다.");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		// }
		@mkdir($this->getDataFolder());
		$this->config['config'] = new Config($this->getDataFolder() . "config.yml", Config::YAML, [
			"plugin-prefix" => "§e[ §f시스템 §e]",
			"block-open-msg" => "§f해당 상자는 §e{OWNER}님§f의 것으로 열 수 없습니다.",
			"block-break-msg" => "§f해당 상자는 §e{OWNER}님§f의 것으로 부실 수 없습니다."
		]);
		$this->db['config'] = $this->config['config']->getAll();
		$this->config['list'] = new Config($this->getDataFolder() . "list.yml", Config::YAML);
		$this->db['list'] = $this->config['list']->getAll();

		$this->config['data'] = new Config($this->getDataFolder() . "data.yml", Config::YAML);
		$this->db['data'] = $this->config['data']->getAll();

		$this->areaProvider = AreaLoader::getInstance()->getAreaManager();
	}

	protected function onDisable() : void{
		$this->config['config']->setAll($this->db['config']);
		$this->config['config']->save();

		$this->config['list']->setAll($this->db['list']);
		$this->config['list']->save();

		$this->config['data']->setAll($this->db['data']);
		$this->config['data']->save();
	}

	/**
	 * @param BlockPlaceEvent $event
	 *
	 * @priority HIGHEST
	 */
	public function onPlace(BlockPlaceEvent $event){
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$block = $event->getBlock();
		$x = $block->getPosition()->getX();
		$y = $block->getPosition()->getY();
		$z = $block->getPosition()->getZ();
		$level = $block->getPosition()->getWorld()->getFolderName();
		$sum = $x . ":" . $y . ":" . $z . ":" . $level;
		$a1 = ($x + 1) . ":" . $y . ":" . $z . ":" . $level;
		$a2 = ($x - 1) . ":" . $y . ":" . $z . ":" . $level;
		$a3 = $x . ":" . $y . ":" . ($z + 1) . ":" . $level;
		$a4 = $x . ":" . $y . ":" . ($z - 1) . ":" . $level;
		if($block->getId() !== BlockLegacyIds::CHEST)
			return;
		if(!isset($this->db['list'][$a1]) && !isset($this->db['list'][$a2]) && !isset($this->db['list'][$a3]) && !isset($this->db['list'][$a4])){
			$this->PASS($sum, $player, $name);
			return;
		}else{
			$target = $this->getTarget($x, $y, $z, $level);
			if($target == $name){
				$this->PASS($sum, $player, $name);
				return;
			}else{
				if(isset($this->db['data'][$target])){
					if(isset($this->db['data'][$target][$name])){
						$this->PASS($sum, $player, $name);
						return;
					}
				}
				$this->msg($player, "§f다른 사람의 상자 옆에는 설치할 수 없습니다.");
				$event->cancel();
			}
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @handleCancelled true
	 */
	public function onTouch(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$block = $event->getBlock();
		$x = $block->getPosition()->getX();
		$y = $block->getPosition()->getY();
		$z = $block->getPosition()->getZ();
		$level = $block->getPosition()->getWorld()->getFolderName();
		$sum = $x . ":" . $y . ":" . $z . ":" . $level;
		if($player->hasPermission(DefaultPermissions::ROOT_OPERATOR))
			return;
		if($event->isCancelled())
			return;
		if($block->getId() !== BlockLegacyIds::CHEST)
			return;
		if(!isset($this->db['list'][$sum]))
			return;
		if($this->db['list'][$sum] == $name)
			return;

		if($this->areaProvider != null){
			if(($area = $this->areaProvider->getArea(new Vector3($x, 0, $z), $level)) instanceof AreaManager){
				if($area->getOwner() == $name){
					return;
				}
			}
		}
		$owner = $this->getOwner($sum);
		if(isset($this->db['data'][$owner])){
			if(isset($this->db['data'][$owner][$name])){
				return;
			}
		}
		$msg = $this->db['config']['block-open-msg'];
		$msg = str_replace('{OWNER}', $owner, $msg);
		$player->sendMessage($msg);
		$event->cancel();
	}

	public function onBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$block = $event->getBlock();
		$x = $block->getPosition()->getX();
		$y = $block->getPosition()->getY();
		$z = $block->getPosition()->getZ();
		$level = $block->getPosition()->getWorld()->getFolderName();
		$sum = $x . ":" . $y . ":" . $z . ":" . $level;
		if($block->getId() !== BlockLegacyIds::CHEST)
			return;
		if(!isset($this->db['list'][$sum]))
			return;

		if($this->db['list'][$sum] == $name){
			unset($this->db['list'][$sum]);
			$this->msg($player, "§f상자가 보호 해제 조치되었습니다.");
			$event->cancel();
			return;
		}

		if($this->areaProvider != null){
			if(($area = $this->areaProvider->getArea(new Vector3($x, 0, $z), $level)) instanceof Area){
				if($area->getOwner() == $name){
					unset($this->db['list'][$sum]);
					$this->msg($player, "§f상자가 보호 해제 조치되었습니다.");
					$event->cancel();
					return;
				}
			}
		}
		$owner = $this->getOwner($sum);
		if(isset($this->db['data'][$owner]) or $player->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
			if(isset($this->db['data'][$owner][$name]) or $player->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
				unset($this->db['list'][$sum]);
				$this->msg($player, "§f상자가 보호 해제 조치되었습니다.");
				$event->cancel();
				return;
			}
		}
		$msg = $this->db['config']['block-break-msg'];
		$msg = str_replace('{OWNER}', $owner, $msg);
		$player->sendMessage($msg);
		$event->cancel();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$name = strtolower($sender->getName());
		if($command == "상자"){
			if(!$sender instanceof Player){
				$sender->sendMessage("§e게임 안에서만 사용이 가능합니다.");
				return true;
			}
			if(!isset($args[0])){
				$args[0] = 'default';
			}
			switch($args[0]){
				case '공유':
					if(!isset($args[1])){
						$this->sendMessage($sender, "§f공유할 닉네임을 적어주세요.");
						return true;
					}
					$sa = strtolower($args[1]);
					if(isset($this->db['data'][$name][$sa])){
						$this->sendMessage($sender, "§f이미 공유되어 있는 플레이어입니다.");
						return true;
					}
					$this->db['data'][$name][$sa] = [];
					$this->sendMessage($sender, "§f성공적으로 내 상자를 §e" . $sa . "님§f에게 공유하였습니다.");
					return true;

				case '공유해제':
					if(!isset($args[1])){
						$this->sendMessage($sender, "§f공유 해제할 닉네임을 적어주세요.");
						return true;
					}
					$sa = strtolower($args[1]);
					if(!isset($this->db['data'][$name][$sa])){
						$this->sendMessage($sender, "§f공유되어 있지 않은 플레이어입니다.");
						return true;
					}
					unset($this->db['data'][$name][$sa]);
					$this->sendMessage($sender, "§f성공적으로 공유를 해제하였습니다.");
					return true;

				case '공유목록':
					if(!isset($this->db['data'][$name])){
						$this->sendMessage($sender, "§f당신이 공유한 플레이어를 찾을 수 없습니다.");
						return true;
					}

					$pls = $this->getPlayers($name);
					$prefix = $this->db['config']['plugin-prefix'];
					$msg = $prefix . " §f";
					foreach($pls as $re){
						$msg .= $re . " ";
					}
					$sender->sendMessage($msg);
					return true;

				default:
					$this->help($sender);
			}
		}
		return true;
	}

	public function msg(Player $player, $message){
		$prefix = "§d<§f시스템§d>";
		$player->sendMessage($prefix . " §f" . $message);
	}

	public function sendMessage(CommandSender $player, $message){
		$prefix = "§d<§f시스템§d>";
		$player->sendMessage($prefix . " §f" . $message);
	}

	public function help(Player $player){
		$prefix = "§d<§f시스템§d>";
		$player->sendMessage($prefix . " §e/상자 공유 <닉네임> §f| 해당 플레이어에게 내 상자를 공유합니다.");
		$player->sendMessage($prefix . " §e/상자 공유해제 <닉네임> §f| 내 상자가 공유된 플레이어를 제거합니다.");
		$player->sendMessage($prefix . " §e/상자 공유목록 §f| 내 상자가 공유된 플레이어 목록을 확인합니다.");
	}

	public function CheckPlayers($target, $name){
		if(!isset($this->db['data'][$target]))
			return false;
		if(isset($this->db['data'][$target][$name]))
			return true;
	}

	public function getPlayers($name){
		$array = [];
		foreach($this->db['data'][$name] as $name => $bool){
			array_push($array, $name);
		}
		return $array;
	}

	public function getOwner($sum){
		return $this->db['list'][$sum];
	}

	public function getTarget($x, $y, $z, $level){
		$a1 = ($x + 1) . ":" . $y . ":" . $z . ":" . $level;
		$a2 = ($x - 1) . ":" . $y . ":" . $z . ":" . $level;
		$a3 = $x . ":" . $y . ":" . ($z + 1) . ":" . $level;
		$a4 = $x . ":" . $y . ":" . ($z - 1) . ":" . $level;
		if(isset($this->db['list'][$a1]))
			$target = $this->db['list'][$a1];
		if(isset($this->db['list'][$a2]))
			$target = $this->db['list'][$a2];
		if(isset($this->db['list'][$a3]))
			$target = $this->db['list'][$a3];
		if(isset($this->db['list'][$a4]))
			$target = $this->db['list'][$a4];

		return $target;
	}

	public function PASS($sum, Player $player, $name){
		$this->db['list'][$sum] = $name;
		$this->msg($player, "§f상자가 보호 조치되었습니다.");
	}
}