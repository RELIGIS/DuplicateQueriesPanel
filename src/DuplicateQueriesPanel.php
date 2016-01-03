<?php

namespace RELIGIS;

use Nette\Database\Connection;
use Nette\Database\Helpers;
use Nette\Database\ResultSet;
use Nette\Utils\Strings;
use Tracy;
use Tracy\Debugger;
use Tracy\IBarPanel;

class DuplicateQueriesPanel implements IBarPanel
{
	private $queries = array();
	private $dupliciteQueries;

	public function __construct(Connection $connection)
	{
		$connection->onQuery[] = array($this, 'logQuery');
	}

	public function logQuery(Connection $connection, $result)
	{
		if(!($result instanceof ResultSet))
		{
			return;
		}

		$queryString = $result->getQueryString();
		if(Strings::startsWith($queryString, 'EXPLAIN'))
		{
			return;
		}

		$source = NULL;
		$trace = $result instanceof \PDOException ? $result->getTrace() : debug_backtrace(PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_IGNORE_ARGS : FALSE);
		foreach($trace as $row)
		{
			if(isset($row['file']) && is_file($row['file']) && !Debugger::getBluescreen()
			                                                            ->isCollapsed($row['file'])
			)
			{
				if((isset($row['function']) && strpos($row['function'], 'call_user_func') === 0) || (isset($row['class']) && is_subclass_of($row['class'], '\\Nette\\Database\\Connection')))
				{
					continue;
				}

				$source = array($row['file'], (int) $row['line']);
				break;
			}
		}

		$sqlQuery = Helpers::dumpSql($queryString, $result->getParameters(), $connection);

		$this->queries[$sqlQuery][] = array($sqlQuery, $source);
	}

	public function getPanel()
	{
		$duplicitesQueries = $this->getDuplicateQueries();
		if(empty($duplicitesQueries))
		{
			return '';
		}

		$panel = '
		<div class="tracy-inner nette-DbDuplicatesQueries">
			<table>
				<tr><th>Count</th><th>SQL Query</th><th>Locations</th></tr>';

				foreach($duplicitesQueries as $query => $dupliciteQuery)
				{
					$panel .= '<tr>';
					$count = count($dupliciteQuery);

					$panel .= '<td>'.$count.'x</td>';
					$panel .= '<td>'.$query.'</td>';

					$panel .= '<td>';
					foreach($dupliciteQuery as $queryInformation)
					{
						$source = $queryInformation[1];
						if(!$source)
						{
							continue;
						}

						$panel .= substr_replace(Tracy\Helpers::editorLink($source[0], $source[1]), ' class="nette-DbConnectionPanel-source"', 2, 0).'<br />';
					}

					$panel .= '</td>';

					$panel .= '</tr>';

				}
		$panel .= '
			</table>
		</div>';

		return $panel;
	}

	public function getTab()
	{
		$dupliciteQueries = $this->getDuplicateQueries();
		$dupliciteQueriesCount = count($dupliciteQueries);

		$tab = '<span title="Nette\Database default">
				<svg viewBox="0 0 2048 2048"><path fill="'.($dupliciteQueriesCount > 0 ? 'red' : '#aaa').'" d="M1024 896q237 0 443-43t325-127v170q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-170q119 84 325 127t443 43zm0 768q237 0 443-43t325-127v170q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-170q119 84 325 127t443 43zm0-384q237 0 443-43t325-127v170q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-170q119 84 325 127t443 43zm0-1152q208 0 385 34.5t280 93.5 103 128v128q0 69-103 128t-280 93.5-385 34.5-385-34.5-280-93.5-103-128v-128q0-69 103-128t280-93.5 385-34.5z"/>
				</svg>
				<span class="tracy-label">';

		if($dupliciteQueriesCount > 0)
		{
			$tab .= $dupliciteQueriesCount.' duplicate '.($dupliciteQueriesCount === 1 ? 'query' : 'queries');
		}
		else
		{
			$tab .= 'No duplicate queries';
		}

		$tab .= '</span></span>';

		return $tab;
	}

	private function getDuplicateQueries()
	{
		if(isset($this->dupliciteQueries))
		{
			return $this->dupliciteQueries;
		}

		$this->dupliciteQueries = array_filter($this->queries, function ($array)
		{
			return count($array) > 1;
		});

		return $this->dupliciteQueries;
	}
}