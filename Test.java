import javax.sql.*;
import java.sql.*;

public class Test {

    public static void main (String[] args) {
        /*
        MysqlDataSource dataSource = new MysqlDataSource();
        dataSource.setUser("scott");
        dataSource.setPassword("tiger");
        dataSource.setServerName("myDBHost.example.org");
        */
        try {
            Class.forName("com.mysql.jdbc.Driver");
        } catch (Exception e) {
            System.out.println("HERE");
            e.printStackTrace();
            return;
        }
        Connection conn = null;
        try {
            System.out.println("Attempting to connect to database . . . ");
            conn =
                /*
               DriverManager.getConnection("jdbc:mysql://localhost:3306/id9121913_database_name", 
                                           "id9121913_databse_username",
                                           "database_password");
                                           */
               DriverManager.getConnection("jdbc:mysql://localhost:3306/database_name", 
                                           "root",
                                           "rootpassword");
        } catch (SQLException ex) {
            // handle any errors
            System.out.println("SQLException: " + ex.getMessage());
            System.out.println("SQLState: " + ex.getSQLState());
            System.out.println("VendorError: " + ex.getErrorCode());
            return;
        }
        System.out.println("we did it?");
    }
}
