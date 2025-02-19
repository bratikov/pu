<?php

ini_set('display_errors', 1);

class Converter
{
	public const IN_PATH = __DIR__.'/../in/';
	public const OUT_PATH = __DIR__.'/../out/';
	public const PKG_SIZE = 200;

	private string $dt;
	private string $ts;

	protected array $out = [];
	protected array $schema = [
		'pckagent' => [
			'pckagentinfo' => [],
			'docagent' => [],
			'docagentdiv' => [],
		],
	];

	protected array $in = [];
	protected array $sources = [
		'tar1',
		'tar2',
		'tar4',
		'tar5',
		'tar7',
		'tar8',
		'tar9',
		'tar14',
		'tar15',
	];

	public function __construct()
	{
		$this->dt = (new DateTime())->format('Y-m-d\TH:i:s');
		$this->ts = (new DateTime())->format('YmdHis');
	}

	public function sendError(string $msg = null): void
	{
		header('HTTP/1.1 400 Bad Request', true, 400);
		echo $msg;
		exit;
	}

	public function getFiles(): void
	{
		if (empty($_FILES['files'])) {
			$this->sendError();
		}

		for ($i = 0; $i < count($_FILES['files']['name']); ++$i) {
			try {
				if (UPLOAD_ERR_OK !== $_FILES['files']['error'][$i]) {
					throw new \Exception('File uploaded with error');
				}

				if (!move_uploaded_file($_FILES['files']['tmp_name'][$i], self::IN_PATH.strtolower($_FILES['files']['name'][$i]))) {
					throw new \Exception('Can\'t move uploaded file');
				}
			} catch (\Throwable $th) {
				$this->sendError($th->getMessage());
			}
		}
	}

	public function process(): void
	{
		foreach ($this->sources as $source) {
			$this->in[$source] = $this->getFileContent($source);
		}

		$i = 0;
		$j = 0;
		$zip = new ZipArchive();
		$res = $zip->open(self::OUT_PATH.'packages.zip', ZipArchive::CREATE);
		if (true !== $res) {
			$this->sendError();
		}
		while (($slice = array_slice($this->in['tar2'], $i, self::PKG_SIZE))) {
			$this->out = $this->schema;
			$this->fillAgentInfo();
			foreach ($slice as $pkg) {
				$this->out['pckagent']['docagent'][] = $this->fillPerson($pkg);
			}
			$i += self::PKG_SIZE;
			// file_put_contents(self::OUT_PATH.$this->getPkgName($j), json_encode($this->out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
			$zip->addFromString($this->getPkgName(++$j), json_encode($this->out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ZipArchive::FL_ENC_UTF_8);
		}
		$zip->close();

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="packages.zip"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: '.filesize(self::OUT_PATH.'packages.zip'));
		readfile(self::OUT_PATH.'packages.zip');
		exit;
	}

	private function fillAgentInfo(): void
	{
		$info = current($this->in['tar1']);
		$this->out['pckagent']['pckagentinfo'] = [
			'vunp' => (string) $info['vunp'],
			'nmns' => (int) $info['nmns'],
			'vexec' => (string) $info['vexec'],
			'vphn' => (string) $info['vphn'],
			'ngod' => (int) $info['ngod'],
			'ntype' => (int) $info['ntype'],
			'dcreate' => (string) $this->dt,
		];
	}

	private function fillPerson(array $person): array
	{
		$out = [
			'docagentinfo' => [],
			'tar4' => [],
			'tar5' => [],
			'tar6' => [],
			'tar7' => [],
			'tar8' => [],
			'tar9' => [],
			'tar10' => [],
			'tar11' => [],
			'tar12' => [],
			'tar13' => [],
			'tar14' => [],
			'ntsumincome' => (float) $this->in['tar15'][$person['cln']]['i4'],
			'ntsumexemp' => (float) $this->in['tar15'][$person['cln']]['i5'],
			'ntsumnotcalc' => 0,
			'nsumstand' => (float) $this->in['tar15'][$person['cln']]['i7'],
			'ntsumsoc' => (float) $this->in['tar15'][$person['cln']]['i8'],
			'ntsumprop' => (float) $this->in['tar15'][$person['cln']]['i9'],
			'ntsumprof' => 0,
			'ntsumsec' => 0,
			'ntsumtrust' => 0,
			'ntsumbank' => 0,
			'ntsumcalcincome' => (float) $this->in['tar15'][$person['cln']]['i14'],
			'ntsumcalcincomediv' => 0,
			'ntsumwithincome' => 0,
			'ntsumwithincomediv' => 0,
		];

		$out['docagentinfo'] = [
			'vfam' => $person['vfam'],
			'vname' => $person['vname'],
			'votch' => $person['votch'],
			'cvdoc' => (string) $person['cvdoc'],
			'cln' => (string) $person['cln'],
			'cstranf' => (string) $person['cstranf'],
			'nrate' => (int) $person['nrate'],
		];

		foreach ($this->sources as $source) {
			if (in_array($source, ['tar1', 'tar2', 'tar15'])) {
				continue;
			}

			$out[$source] = $this->fillPeriods($person['cln'], $source);
		}

		return $out;
	}

	private function fillPeriods(string $cln, string $source): array
	{
		$out = [];

		$periods = $this->in[$source][$cln] ?? [];

		foreach ($periods as $month => $period) {
			if ('tar14' == $source) {
				$out[] = [
					'nmonth' => (int) $period[0]['nmonth'],
					'nsumt' => (float) $period[0]['nsumt'],
					'nsumdiv' => 0,
				];

				continue;
			}

			if (count($period) > 1) {
				$x = [
					'nmonth' => (int) $period[0]['nmonth'],
					'nsummonth' => (float) $period[0]['nsummonth'],
					$source.'sum' => [],
				];
				foreach ($period as $per) {
					$sumIndex = (isset($per['nsum']) ? 'nsum' : 'nsumv');
					$x[$source.'sum'][] = [
						'ncode' => (int) $per['nkode'],
						$sumIndex => (float) $per[$sumIndex],
					];
				}
				$out[] = $x;
			} else {
				$sumIndex = isset($period[0]['nsum']) ? 'nsum' : 'nsumv';
				$out[] = [
					'nmonth' => (int) $period[0]['nmonth'],
					'nsummonth' => (float) $period[0]['nsummonth'],
					$source.'sum' => [[
						'ncode' => (int) $period[0]['nkode'],
						$sumIndex => (float) $period[0][$sumIndex],
					]],
				];
			}
		}

		return $out;
	}

	private function getFileContent(string $source): array
	{
		$content = [];
		$db = dbase_open(Converter::IN_PATH.$source.'.dbf', DBASE_RDONLY);
		if ($db) {
			for ($i = 1; $i <= dbase_numrecords($db); ++$i) {
				$record = dbase_get_record_with_names($db, $i);
				$parsedRecord = [];
				foreach ($record as $key => $field) {
					$parsedRecord[strtolower($key)] = trim(iconv('cp866', 'utf-8', $field));
				}
				if (1 == $parsedRecord['deleted']) {
					continue;
				}
				if (array_key_exists('cln', $parsedRecord) && !in_array($source, ['tar1', 'tar2'])) {
					if ('tar15' == $source) {
						$content[$parsedRecord['cln']] = $parsedRecord;
					} else {
						$content[$parsedRecord['cln']][$parsedRecord['nmonth']][] = $parsedRecord;
					}
				} else {
					$content[] = $parsedRecord;
				}
			}
		}
		dbase_close($db);

		return $content;
	}

	private function getPkgName(int $pkgNumber): string
	{
		return sprintf(
			'D%s_%s_1_0_%s_%04d.json',
			$this->out['pckagent']['pckagentinfo']['vunp'],
			date('Y'),
			$this->ts,
			$pkgNumber
		);
	}

	public function __destruct()
	{
		unlink(self::OUT_PATH.'packages.zip');
		array_map('unlink', glob(self::IN_PATH.'*.dbf'));
	}
}

$converter = new Converter();
$converter->getFiles();
$converter->process();
