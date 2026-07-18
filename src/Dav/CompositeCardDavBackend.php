<?php

namespace Platform\Core\Dav;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\BackendInterface;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

/**
 * Aggregiert die CardDAV-Backends aller registrierten carddav-Module unter EINEM
 * AddressBookRoot. Ein einziger CardDAV-Account zeigt alle Adressbücher aller
 * Module. Collection-IDs/URIs werden per Modul-Key genamespaced.
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class CompositeCardDavBackend extends AbstractBackend
{
    private const SEP = "\x1f";

    /**
     * @param  array<string, BackendInterface>  $backends  Modul-Key => CardDAV-Backend
     */
    public function __construct(
        private readonly array $backends,
        private readonly DavContext $context,
    ) {
    }

    /**
     * Ist ein Modul für das aktive Abo freigegeben? (module=null → alle).
     */
    private function allows(string $key): bool
    {
        $scope = $this->context->subscription()->module;

        return $scope === null || $scope === $key;
    }

    public function getAddressBooksForUser($principalUri)
    {
        $addressBooks = [];

        foreach ($this->backends as $key => $backend) {
            if (! $this->allows($key)) {
                continue;
            }

            foreach ($backend->getAddressBooksForUser($principalUri) as $book) {
                $book['id'] = $key.self::SEP.$book['id'];
                $book['uri'] = $key.'-'.$book['uri'];
                $addressBooks[] = $book;
            }
        }

        return $addressBooks;
    }

    public function getCards($addressbookId)
    {
        [$backend, $innerId] = $this->route($addressbookId);

        return $backend->getCards($innerId);
    }

    public function getCard($addressBookId, $cardUri)
    {
        [$backend, $innerId] = $this->route($addressBookId);

        return $backend->getCard($innerId, $cardUri);
    }

    public function getMultipleCards($addressBookId, array $uris)
    {
        [$backend, $innerId] = $this->route($addressBookId);

        return $backend->getMultipleCards($innerId, $uris);
    }

    // ---- read-only -------------------------------------------------

    public function updateAddressBook($addressBookId, PropPatch $propPatch)
    {
        // Read-only.
    }

    public function createAddressBook($principalUri, $url, array $properties)
    {
        throw new Forbidden('Das Adressbuch ist schreibgeschützt.');
    }

    public function deleteAddressBook($addressBookId)
    {
        throw new Forbidden('Das Adressbuch ist schreibgeschützt.');
    }

    public function createCard($addressBookId, $cardUri, $cardData)
    {
        throw new Forbidden('Das Adressbuch ist schreibgeschützt.');
    }

    public function updateCard($addressBookId, $cardUri, $cardData)
    {
        throw new Forbidden('Das Adressbuch ist schreibgeschützt.');
    }

    public function deleteCard($addressBookId, $cardUri)
    {
        throw new Forbidden('Das Adressbuch ist schreibgeschützt.');
    }

    /**
     * @return array{0: BackendInterface, 1: string|int}
     */
    private function route($addressBookId): array
    {
        $parts = explode(self::SEP, (string) $addressBookId, 2);
        if (count($parts) !== 2 || ! isset($this->backends[$parts[0]]) || ! $this->allows($parts[0])) {
            throw new NotFound('Adressbuch nicht gefunden.');
        }

        return [$this->backends[$parts[0]], $parts[1]];
    }
}
