<?php

namespace Hwkdo\IntranetAppMsgraph\Services;

use Hwkdo\MsGraphLaravel\Interfaces\MsGraphGroupServiceInterface;

/**
 * Delegiert alle Group-Operationen an MsGraphGroupServiceInterface.
 * Kann für app-spezifische Erweiterungen genutzt werden.
 */
class GraphGroupService
{
    public function __construct(private readonly MsGraphGroupServiceInterface $groupService) {}

    public function getGroupIdByName(string $name): ?string
    {
        return $this->groupService->getGroupIdByName($name);
    }

    /**
     * @return array<int, array{id: string, upn: string, displayName: string}>
     */
    public function getGroupMembers(string $groupId): array
    {
        return $this->groupService->getGroupMembers($groupId);
    }

    public function addUserToGroup(string $groupId, string $userId): bool
    {
        return $this->groupService->addUserToGroup($groupId, $userId);
    }
}
