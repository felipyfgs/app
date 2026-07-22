package catalog

import (
	"os"
	"reflect"
	"sort"
	"strings"
	"testing"

	"go.mau.fi/whatsmeow"
)

func TestClientMethodCatalogMatchesPinnedWhatsmeowSurface(t *testing.T) {
	t.Parallel()

	clientType := reflect.TypeOf((*whatsmeow.Client)(nil))
	actual := make([]string, 0, clientType.NumMethod())
	for index := 0; index < clientType.NumMethod(); index++ {
		actual = append(actual, clientType.Method(index).Name)
	}
	sort.Strings(actual)

	expected := make([]string, 0, len(ClientMethods))
	for name := range ClientMethods {
		expected = append(expected, name)
	}
	sort.Strings(expected)

	if !reflect.DeepEqual(expected, actual) {
		t.Fatalf("client method catalog drifted from whatsmeow\nexpected: %v\nactual:   %v", expected, actual)
	}
	if len(actual) != 135 {
		t.Fatalf("expected the pinned Client surface to contain 135 methods, got %d", len(actual))
	}
}

func TestCatalogEntriesHaveDispositionOwnerAndEvidence(t *testing.T) {
	t.Parallel()

	allowed := map[Disposition]bool{
		Baseline: true, Implemented: true, Internal: true, Excluded: true, Deprecated: true,
	}
	assertEntry := func(kind, name string, entry Entry) {
		t.Helper()
		if entry.Source != UpstreamCommit {
			t.Errorf("%s %s has unexpected source %q", kind, name, entry.Source)
		}
		if entry.Scope == "" || entry.Owner == "" || entry.Evidence == "" {
			t.Errorf("%s %s must define scope, owner and evidence: %#v", kind, name, entry)
		}
		if !allowed[entry.Disposition] {
			t.Errorf("%s %s has invalid disposition %q", kind, name, entry.Disposition)
		}
		if (entry.Disposition == Baseline || entry.Disposition == Implemented || entry.Disposition == Internal) &&
			!strings.Contains(entry.Evidence, "_test.go") {
			t.Errorf("%s %s has no concrete test evidence: %q", kind, name, entry.Evidence)
		}
	}

	for name, entry := range ClientMethods {
		assertEntry("method", name, entry)
	}
	for name, entry := range EventTypes {
		assertEntry("event", name, entry.Entry)
		if entry.Type == nil || entry.Type.Name() != name {
			t.Errorf("event %s compile reference points to %v", name, entry.Type)
		}
	}
	if len(EventTypes) != 74 {
		t.Fatalf("expected 74 public event compile references, got %d", len(EventTypes))
	}
}

func TestCatalogPinsWhatsmeowVersionInGoMod(t *testing.T) {
	t.Parallel()

	goMod, err := os.ReadFile("../../../go.mod")
	if err != nil {
		t.Fatalf("read gateway go.mod: %v", err)
	}
	pinnedRequirement := UpstreamModule + " " + UpstreamVersion
	if !strings.Contains(string(goMod), pinnedRequirement) {
		t.Fatalf("gateway go.mod must pin %q", pinnedRequirement)
	}
}
