import java.util.Scanner;
import java.io.File;

public class EvaluateUnitTests {

    final static String expectedOutputsFile = "test_expected_outputs.txt";
    final static String actualOutputsFile = "test_outputs.txt";
    final static String namesFile = "test_names.txt";
    final static String unitTestBarrierFile = "unit_test_barrier.txt";
    static String barrier;

    public static void main (String[] args) {
        Scanner expected = null;
        Scanner actual = null;
        Scanner names = null;
        try {
            expected = new Scanner(new File(expectedOutputsFile));
            actual = new Scanner(new File(actualOutputsFile));
            names = new Scanner(new File(namesFile));
            Scanner b = new Scanner(new File(unitTestBarrierFile));
            barrier = b.nextLine();
            b.close();
        } catch (Exception e) {}
        int result = 0;
        int numPassed = 0;
        int numFailed = 0;
        if (!matchFive(actual)) {
            System.err.println("Error connecting to database");
            return;
        }
        for (int testNum = 0; names.hasNextLine(); testNum++) {
            System.out.print(names.nextLine() + " . . . ");
            while ((result = matchLines(actual, expected)) == 0);
            if (result == 1) {
                numPassed++;
                System.out.println("PASSED");
            } else {
                numFailed++;
                System.out.println("FAILED");
            }
        }
        actual.close();
        expected.close();
        names.close();
        System.out.println(numPassed + " tests passed.");
        System.out.println(numFailed + " tests failed.");
    }

    public static boolean matchFive(Scanner actual) {
        String[] conn = {
            "Attempting to connect to database...",
            "Connected successfully",
            "Loading classes...",
            "Classes loaded",
            ""
        };
        int i = 0;
        for (; actual.hasNextLine() && i < 5; i++) {
            if (!actual.nextLine().equals(conn[i])) return false;
        }
        return (i == 5);
    }
    
    // return 1 if test passed
    // return 0 if line passed
    // return -1 if test failed;
    public static int matchLines(Scanner actual, Scanner expected) {
        String a = actual.nextLine();
        String e = expected.nextLine();
        if (a.equals(barrier)) {
            if (e.equals(barrier)) return 1;
            readUntilNext(expected);
            return -1;
        }
        if (e.equals(barrier)) {
            readUntilNext(actual);
            return -1;
        }
        if (patternMatch(e, a)) return 0;
        readUntilNext(actual);
        readUntilNext(expected);
        return -1;
    }

    public static boolean patternMatch(String e, String a) {
        if (e.equals(a)) return true;
        int i = 0;
        int j = 0;
        for (; i < e.length() && j < a.length(); i++) {
            if (e.charAt(i) != a.charAt(j)) {
                if (e.charAt(i) != '#') return false;
                if (!Character.isDigit(a.charAt(j))) return false;
                if (a.charAt(j) == '0') return false;
                while (++j < a.length() && Character.isDigit(a.charAt(j)));
            } else j++;
        }
        return j == a.length() && i == e.length();
    }

    public static void readUntilNext(Scanner s) {
        while (s.hasNextLine()) {
            String line = s.nextLine();
            if (line.equals(barrier)) return;
        }
    }
}
