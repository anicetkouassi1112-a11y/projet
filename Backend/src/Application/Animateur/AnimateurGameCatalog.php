<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

use Patro\Domain\Inscription\Repository\SectionRepository;
use Patro\Domain\Jeu\Repository\JeuRepository;

final class AnimateurGameCatalog
{
    public function __construct(
        private SectionRepository $sections,
        private JeuRepository $games
    ) {
    }

    /** @return array{genre:string,types:list<array<string,mixed>>} */
    public function execute(int $sectionId): array
    {
        return [
            'genre' => strtolower(trim((string) ($this->sections->findGenreById($sectionId) ?? ''))),
            'types' => $this->games->types(),
        ];
    }
}
