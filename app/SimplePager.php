<?php

declare(strict_types=1);

class SimplePager
{
    public int $limit;       // Page size
    public int $page;        // Current page
    public int $item_count;  // Total item count
    public int $page_count;  // Total page count

    /** @var array<int, array<string, mixed>> */
    public array $result;    // Result set (array of records)
    public int $count;       // Item count on the current page

    public function __construct(
        string $query,
        array $params,
        int|string $limit,
        int|string $page
    ) {
        global $_db;

        // Set [limit] and [page]
        $this->limit = ctype_digit((string) $limit) ? max((int) $limit, 1) : 10;
        $this->page = ctype_digit((string) $page) ? max((int) $page, 1) : 1;

        // Set [item count]
        $q = preg_replace('/SELECT.+?FROM/is', 'SELECT COUNT(*) FROM', $query, 1);

        if ($q === null) {
            throw new RuntimeException('Failed to build count query.');
        }

        $stm = $_db->prepare($q);
        $stm->execute($params);

        $this->item_count = (int) $stm->fetchColumn();

        // Set [page count]
        $this->page_count = (int) ceil($this->item_count / $this->limit);

        // Make sure requested page is within range
        $this->page = min($this->page, max($this->page_count, 1));

        // Calculate offset
        $offset = ($this->page - 1) * $this->limit;

        // Set [result]
        $stm = $_db->prepare($query . " LIMIT $offset, $this->limit");

        $stm->execute($params);

        $this->result = $stm->fetchAll(PDO::FETCH_ASSOC);

        // Set [count]
        $this->count = count($this->result);
    }

    public function html(string $href = '', string $attr = ''): void
    {
        if (!$this->result) {
            return;
        }

        $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        $attr = htmlspecialchars($attr, ENT_QUOTES, 'UTF-8');

        // Generate pager (HTML)
        $prev = max($this->page - 1, 1);
        $next = min($this->page + 1, $this->page_count);

        echo "<nav class='pagination' $attr>";
        echo "<a href='?page=1&$href'>First</a>";
        echo "<a href='?page=$prev&$href'>Previous</a>";

        for ($p = 1; $p <= $this->page_count; $p++) {
            $c = ($p === $this->page) ? 'active' : '';

            echo "<a href='?page=$p&$href' class='$c'>$p</a>";
        }

        echo "<a href='?page=$next&$href'>Next</a>";
        echo "<a href='?page=$this->page_count&$href'>Last</a>";
        echo "</nav>";
    }
}
