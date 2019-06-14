import java.util.Scanner;
import java.io.File;
import java.io.PrintStream;

enum State {
    NEW_TEST,
    NAME,
    INPUT,
    OUTPUT
}

public class ParseUnitTests {

    final static String testCasesFile = "unit_tests.txt";
    final static String inputsFile = "test_inputs.txt";
    final static String outputsFile = "test_expected_outputs.txt";
    final static String namesFile = "test_names.txt";
    final static String unitTestBarrierFile = "unit_test_barrier.txt";
    static String barrier;

    public static void main (String[] args) {
        Scanner in = null;
        PrintStream inputs = null;
        PrintStream outputs = null;
        PrintStream names = null;
        try {
            in = new Scanner(new File(testCasesFile));
            inputs = new PrintStream(new File(inputsFile));
            outputs = new PrintStream(new File(outputsFile));
            names = new PrintStream(new File(namesFile));
            Scanner b = new Scanner(new File(unitTestBarrierFile));
            barrier = b.nextLine();
            b.close();
        } catch (Exception e) {
            e.printStackTrace();
            System.exit(1);
        }
        boolean success = parseTests(in, inputs, outputs, names);
        in.close();
        if (!success) System.exit(1);
    }

    public static boolean parseTests(Scanner in, PrintStream inputs, PrintStream outputs, PrintStream names) {
        int testNum = 0;
        State st = State.NEW_TEST;
        boolean nameFound = false;
        int lineNum = 1;
        for (; in.hasNextLine(); lineNum++) {
            String line = in.nextLine();
            if (line.equals("")) {
                if (st == State.OUTPUT) {
                    inputs.println(barrier);
                    outputs.println(barrier);
                    nameFound = false;
                    st = State.NEW_TEST;
                    continue;
                }
                return parseError("unexpected empty line", lineNum);
            }
            switch(st) {
                case NEW_TEST:
                    if (line.equals("NAME")) st = State.NAME;
                    else return parseError("expected beginning of new test", lineNum);
                    break;
                case NAME:
                    if (!nameFound) {
                        nameFound = true;
                        names.println(line); 
                        break;
                    }
                    if (line.equals("INPUT")) st = State.INPUT;
                    else return parseError("expected \"INPUT\" keyword", lineNum);
                    break;
                case INPUT:
                    if (line.equals("OUTPUT")) st = State.OUTPUT;
                    else inputs.println(line);
                    break;
                case OUTPUT:
                    outputs.println(line);
                    break;
            }
        }
        if (st != State.NEW_TEST && st != State.OUTPUT) return parseError("Unexpected end of file", lineNum);
        inputs.println("quit");
        return true;
    }
    
    public static boolean parseError(String message, int lineNum) {
        System.err.println("Parse error on line " + lineNum + ": " + message);
        return false;
    }
}

