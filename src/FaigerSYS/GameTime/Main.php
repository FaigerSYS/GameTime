<?PHP
namespace FaigerSYS\GameTime;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as CLR;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\RemoteConsoleCommandSender;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class Main extends PluginBase implements Listener {
	private $config = array(), $buffer = array(), $no_perm;
	
	public function onEnable() {
		$this->getLogger()->info(CLR::GOLD . 'GameTime loading...');
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->config = new Config($this->getDataFolder() . "players.yml", Config::YAML);
		$this->no_perm = CLR::RED . "You don't have permission to use this command...";
		
		$this->getLogger()->info(CLR::GOLD . 'GameTime loaded!');
	}
	
	public function onPlayerJoin(PlayerJoinEvent $e) {
		$name = strtolower($e->getPlayer()->getName());
		$this->buffer[$name] = time();
	}
	
	public function onPlayerLeave(PlayerQuitEvent $e) {
		$name = strtolower($e->getPlayer()->getName());
		if (isset($this->buffer[$name])) {
			$time = time() - $this->buffer[$name];
			if(!$this->config->exists($name)) $this->config->set($name, $time);
			else $this->config->set($name, $time + $this->config->get($name));
			$this->config->save();
			unset($this->buffer[$name]);
		}
	}
	
	public function onCommand(CommandSender $sender, Command $cmd, $lbl, array $args){
		if($cmd->getName() == 'gametime') {
			if(count($args) == 0) {
				return $sender->sendMessage(
					CLR::YELLOW . 'GameTime' . CLR::GOLD . " help:\n" .
					CLR::AQUA . '/gt now' . CLR::BLUE . ' - ' . CLR::AQUA . "get the duration of that session\n" .
					CLR::AQUA . '/gt all ' . CLR::BLUE . ' - ' . CLR::AQUA . "get the total time of the game\n" .
					CLR::AQUA . 'To get values of another player, just type his name in end of command.'
				);
			} elseif ($args[0] == 'now') {
				if (!$sender->hasPermission('gametime.now'))
					return $sender->sendMessage($this->no_perm);
				
				if (isset($args[1])) {
					if (!$sender->hasPermission('gametime.now.other'))
						return $sender->sendMessage($this->no_perm);
						
					$name = $args[1];
					if (($p = $this->getServer()->getPlayer($name)) !== null)
						$name = $p->getName();
					$time = explode(':', $this->getSessionTime($name, '%H%:%i%:%s%'));
					if (count($time) != 3)
						return $sender->sendMessage(CLR::RED . 'Player not online now.');
					$sender->sendMessage(CLR::GOLD . $name . "'s duration of session: " . $time[0] . ' hor., ' . $time[1] . ' min., ' . $time[2] . 'sec.');
				} elseif ($sender instanceof ConsoleCommandSender || $sender instanceof RemoteConsoleCommandSender)
					return $sender->sendMessage(CLR::RED . 'You cannot get duration of console session. But you can get duration of players sessions.');
				else {
					if (!$sender->hasPermission('gametime.now.self'))
						return $sender->sendMessage($this->no_perm);
					
					$name = $sender->getName();
					$time = explode(':', $this->getSessionTime($name, '%H%:%i%:%s%'));
					return $sender->sendMessage(CLR::GOLD . 'Your duration of session: ' . $time[0] . ' hor., ' . $time[1] . ' min., ' . $time[2] . ' sec.');
				}
			} elseif ($args[0] == 'all') {
				if (!$sender->hasPermission('gametime.all'))
					return $sender->sendMessage($this->no_perm);
				
				if (isset($args[1])) {
					if (!$sender->hasPermission('gametime.all.other'))
						return $sender->sendMessage($this->no_perm);
					
					$name = $args[1];
					if (($p = $this->getServer()->getPlayer($name)) !== null)
						$name = $p->getName();
					$time = explode(':', $this->getAllTime($name, '%H%:%i%:%s%'));
					if (count($time) != 3)
						return $sender->sendMessage(CLR::RED . 'No data about this player.');
					$sender->sendMessage(CLR::GOLD . $name . "'s total time of the game: " . $time[0] . ' hor., ' . $time[1] . ' min., ' . $time[2] . ' sec.');
				} elseif ($sender instanceof ConsoleCommandSender || $sender instanceof RemoteConsoleCommandSender) {
					return $sender->sendMessage(CLR::RED . 'You cannot get duration of console sessions. But you can get duration of players sessions.');
				} else {
					if (!$sender->hasPermission('gametime.all.self'))
						return $sender->sendMessage($this->no_perm);
						
					$name = $sender->getName();
					$time = explode(':', $this->getAllTime($name, '%H%:%i%:%s%'));
					return $sender->sendMessage(CLR::GOLD . 'Your total time of the game: ' . $time[0] . ' hor., ' . $time[1] . ' min., ' . $time[2] . ' sec.');
				}
			} else {
				return $sender->sendMessage(
					CLR::YELLOW . 'GameTime' . CLR::GOLD . " help:\n" .
					CLR::AQUA . '/gt now' . CLR::BLUE . ' - ' . CLR::AQUA . "get the duration of that session\n" .
					CLR::AQUA . '/gt all ' . CLR::BLUE . ' - ' . CLR::AQUA . "get the duration of all game time\n" .
					CLR::AQUA . 'To get values of another player, just type his name in end of command.'
				);
			}
		}
	}
	
	//$name - player's name //$format - string {%d% - days, %H% - hours (0-24), %i% - minutes, %s% - seconds}
	public function getSessionTime($name, $format = false) {
		$name = strtolower($name);
		if (!isset($this->buffer[$name]))
			return false;
		$time = time() - $this->buffer[$name];
		if (!$format) return "$time";
		else return $this->getFormatedTime($time, $format);
	}
	
	//$name - player's name //$format - string {%d% - days, %H% - hours (0-24), %i% - minutes, %s% - seconds}
	public function getAllTime($name, $format = false) {
		$name = strtolower($name);
		if (!isset($this->buffer[$name])) {
			if (!$this->config->exists($name)) return false;
			else $x = $this->config->get($name);
		} else {
			if (!$this->config->exists($name)) $x = time() - $this->buffer[$name];
			else $x = time() - $this->buffer[$name] + $this->config->get($name);
		}
		if (!$format) return "$x";
		else return $this->getFormatedTime($x, $format);
	}
	
	private function getFormatedTime($a, $format) {
		$d = $H = $i = 0;
		if (strpos($format, 'd') !== false) {
			$d = floor($a / 86400);
		} if (strpos($format, 'H') !== false) {
			$H = floor(($a - $d*86400) / 3600);
		} if (strpos($format, 'i') !== false) {
			$i = floor(($a - $d*86400 - $H*3600) / 60);
		} $s = $a - $d*86400 - $H*3600 - $i*60;
		return str_replace(array('%d%', '%H%', '%i%', '%s%'), array(strlen($d) == 1 ? '0' . $d : $d, strlen($H) == 1 ? '0' . $H : $H, strlen($i) == 1 ? '0' . $i : $i, strlen($s) == 1 ? '0' . $s : $s), $format);
	}
	
	public function onDisable() {
		$this->getLogger()->info(CLR::GOLD . 'Disabling GameTime...');
		
		foreach ($this->buffer as $name => $time) {
			$time = time() - $time;
			if(!$this->config->exists($name)) $this->config->set($name, $time);
			else $this->config->set($name, $time + $this->config->get($name));
			unset($this->buffer[$name]);
		}
		$this->config->save();
		
		$this->getLogger()->info(CLR::GOLD . 'GameTime disabled!');
	}
}
