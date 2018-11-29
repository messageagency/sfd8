<?php

namespace Drupal\salesforce\Commands;

use Consolidation\OutputFormatters\Formatters\TableFormatter;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Format QueryResult metadata.
 */
class QueryResultTableFormatter extends TableFormatter {

  /**
   * {@inheritdoc}
   */
  public function validDataTypes() {
    return [
      new \ReflectionClass('\Drupal\salesforce\Commands\QueryResult'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function writeMetadata(OutputInterface $output, $query, FormatterOptions $options) {
    $output->writeln(str_pad(' ', 10 + strlen($query->getPrettyQuery()), '-'));
    $output->writeln(dt('  Size: !size', ['!size' => $query->getSize()]));
    $output->writeln(dt('  Total: !total', ['!total' => $query->getTotal()]));
    $output->writeln(dt('  Query: !query', ['!query' => $query->getPrettyQuery()]));
  }

}
